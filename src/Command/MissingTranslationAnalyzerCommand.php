<?php

namespace Condoedge\Utils\Command;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Condoedge\Utils\Services\Translation\TranslationKeyFilter;

class MissingTranslationAnalyzerCommand extends Command
{
    /**
     * Map of translation keys to their file locations
     *
     * @var array
     */
    private $keyFileMap = [];

    /**
     * Prefixes detected from dynamic translation calls.
     * Any JSON key starting with one of these is assumed to be used dynamically.
     *
     * @var array<string, true>
     */
    private $dynamicPrefixes = [];

    /** @var TranslationKeyFilter|null */
    private $keyFilter;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:missing-translation-analyzer-command
                        {--exclude-key=* : Add keys to exclude list}
                        {--show-excluded : Show current excluded keys}
                        {--reset-excluded : Reset excluded keys to default}
                        {--json : Output missing translations as JSON with file locations}
                        {--merge-vendor : Merge translations from vendor packages (condoedge/*, kompo/*) into project}
                        {--include-plain-text : Include plain-text keys (letters+spaces only) — more coverage, more false positives}
                        {--locale=* : Locales to check (default: config app.supported_locales or [en, fr])}
                        {--check-empty-values : List keys with empty or self-referencing values in *.json files}
                        {--check-obsolete : List keys present in JSON files but never used in code}
                        {--diff-locales : Show keys present in one locale but missing in others}
                        {--universal-detection : Capture any quoted string that LOOKS like a translation key — scoped to UI files only (Kompo/Blade/Vue/Enums with label). Higher coverage, more false positives.}
                        {--include-triaged : Include keys already marked fixed or ignored in the DB (by default, triaged rows are hidden from the report).}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze and find missing translation keys in the application';

    /**
     * Single source of truth: every callable that takes a translation key as first argument.
     *
     * Each entry describes ONE function; regexes for literal capture, named-arg capture,
     * and dynamic-prefix extraction are all derived from this list.
     *
     *   'regex'       → regex fragment matching the callable name (with escaping done)
     *   'named_arg'   → supports PHP 8 named args `func(key: '...')`
     *   'lookbehind'  → extra negative lookbehind to avoid false opens (e.g. `$t` shouldn't match `foo$t`)
     *   'multi_arg'   → the key is followed by other args (e.g. `trans_choice('key', $n)`)
     */
    private const TRANSLATION_FUNCTIONS = [
        ['regex' => '__',                                                     'named_arg' => true,  'lookbehind' => ''],
        ['regex' => 'trans',                                                  'named_arg' => true,  'lookbehind' => '(?<![a-zA-Z])'],
        ['regex' => 'trans_choice',                                           'named_arg' => true,  'lookbehind' => '',              'multi_arg' => true],
        ['regex' => '@lang',                                                  'named_arg' => false, 'lookbehind' => ''],
        ['regex' => 'Lang::get',                                              'named_arg' => false, 'lookbehind' => ''],
        ['regex' => '\$this->translator',                                     'named_arg' => false, 'lookbehind' => ''],
        ['regex' => '_[A-Z][a-zA-Z]+',                                        'named_arg' => false, 'lookbehind' => '', 'multi_arg' => true],
        ['regex' => '_',                                                      'named_arg' => false, 'lookbehind' => '(?<![a-zA-Z])'], // gettext-style
        ['regex' => '\$t',                                                    'named_arg' => false, 'lookbehind' => '(?<![a-zA-Z])'], // Vue
        ['regex' => 'i18n\.t',                                                'named_arg' => false, 'lookbehind' => '(?<![a-zA-Z])'], // Vue-i18n
        ['regex' => 'throwValidationError\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*',   'named_arg' => false, 'lookbehind' => '',               'is_raw_prefix' => true],
    ];

    /**
     * Extra literal patterns that aren't function calls (PHP properties, Vue directives, array values, …).
     */
    private const EXTRA_LITERAL_PATTERNS = [
        'titles'             => '/protected\s+\$_Title\s*=\s*[\'"](.+?)[\'"]\s*;/u',
        'vue_directive_t'    => '/v-t\s*=\s*[\'"]([^\'"]+)[\'"]/u',
        'array_value'        => '/=>\s*[\'"]([a-z][a-z0-9_-]*\.[a-z][a-z0-9._-]*)[\'"]/iu',
    ];

    /**
     * File exclusion configuration
     */
    private const FILE_EXCLUSIONS = [
        'files' => [
            'composer.lock', 'package-lock.json', 'yarn.lock', 'webpack.mix.js',
            'tailwind.config.js', 'vite.config.js', '_ide_helper.php', 'server.php', 'artisan'
        ],
        'patterns' => [
            '/\.min\.(js|css)$/', '/\.lock$/', '/Test\.php$/', '/_test\.php$/',
            '/Migration\.php$/', '/Seeder\.php$/', '/Factory\.php$/'
        ],
        'paths' => [
            '/vendor/', '/node_modules/', '/storage/', '/bootstrap/cache/',
            '/public/build/', '/public/hot'
        ]
    ];

    /**
     * File-like extensions to treat as filenames and ignore as translation keys
     */
    private const FILE_LIKE_EXTENSIONS = [
        'txt', 'pdf', 'xlsx', 'xls', 'csv',
        'doc', 'docx', 'ppt', 'pptx',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
        'zip', 'rar', 'tar', 'gz', '7z',
        'mp3', 'mp4', 'mov', 'avi', 'mkv', 'webm',
        'log', 'md', 'json', 'xml', 'html', 'htm', 'css', 'js', 'ts',
        'yml', 'yaml', 'ini'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('show-excluded')) {
            $this->showExcludedKeys();
            return;
        }

        if ($this->option('reset-excluded')) {
            $this->resetExcludedKeys();
            return;
        }

        if ($excludeKeys = $this->option('exclude-key')) {
            $this->addToExcludeList($excludeKeys);
            return;
        }

        if ($this->option('merge-vendor')) {
            $this->mergeVendorTranslations();
            return;
        }

        if ($this->option('check-empty-values')) {
            $this->checkEmptyValues();
            return;
        }

        if ($this->option('check-obsolete')) {
            $keys = $this->extractAllTranslationKeys();
            $this->checkObsoleteKeys($keys);
            return;
        }

        if ($this->option('diff-locales')) {
            $this->diffLocales();
            return;
        }

        $keys = $this->extractAllTranslationKeys();
        $this->indexKeys($keys);
        $this->checkMissingTranslations($keys);
    }
    
    private function extractAllTranslationKeys()
    {
        $linkedPackages = $this->loadLinkedPackages();

        $finder = new Finder();
        $finder->files()
            ->in(base_path())
            ->name('*.php')
            ->name('*.blade.php')
            ->name('*.vue')
            ->name('*.js')
            ->name('*.ts')
            ->exclude('node_modules')
            ->exclude('storage')
            ->exclude('bootstrap/cache')
            ->exclude('public')
            ->notName('*.lock')
            ->notName('*.min.js')
            ->notName('*.min.css')
            ->notPath('*/migrations/*')
            ->notPath('*/seeders/*')
            ->notPath('*/factories/*')
            ->notPath('*/tests/*')
            ->notPath('*/Test*')
            ->notPath('*/_ide_helper*')
            ->notPath('*/config/cache/*')
            ->notPath('*/lang/*')
            ->notPath('*/resources/lang/*');

        // Add any user-linked local packages (folders outside base_path()) as additional roots.
        foreach ($linkedPackages as $path) {
            if (is_dir($path)) {
                $finder->in($path);
            }
        }

        $linkedRealPaths = array_filter(array_map('realpath', $linkedPackages));

        $files = $finder->filter(function (\SplFileInfo $file) use ($linkedRealPaths) {
            $path = $file->getRealPath();

            // Files inside a user-linked package are always included.
            foreach ($linkedRealPaths as $linked) {
                if ($linked && strpos($path, $linked . DIRECTORY_SEPARATOR) === 0) {
                    return true;
                }
            }

            // If file is not in vendor, include it
            if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
                return true;
            }

            // If file is in vendor/condoedge or vendor/kompo, include it
            if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'condoedge' . DIRECTORY_SEPARATOR) !== false ||
                strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'kompo' . DIRECTORY_SEPARATOR) !== false) {
                return true;
            }

            // Exclude all other vendor files
            return false;
        });

        $keys = [];
        $this->keyFileMap = []; // Store key to file mappings

        foreach ($files as $file) {
            // Skip files that shouldn't contain translations
            if ($this->shouldSkipFile($file->getFilename(), $file->getRealPath())) {
                continue;
            }

            $content = file_get_contents($file->getRealPath());
            $this->collectDynamicPrefixes($content);
            $fileKeys = $this->extractKeysFromContent($content, $file->getRealPath());

            foreach ($fileKeys as $keyData) {
                $key = $keyData['key'];
                $keys[] = $key;

                // Store file location and context for each key
                if (!isset($this->keyFileMap[$key])) {
                    $this->keyFileMap[$key] = [];
                }
                $this->keyFileMap[$key][] = [
                    'file' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath()),
                    'line' => $keyData['line'],
                    'context' => $keyData['context']
                ];
            }
        }

        return array_unique($keys);
    }

    private function indexKeys($keys)
    {
        $this->info("Indexing " . count($keys) . " translation keys...");
    
        // Store in cache for quick access
        cache(['translation_keys' => $keys], now()->addDay());
        
        // Also save to file for reference
        file_put_contents(
            storage_path('app/translation_keys.json'), 
            json_encode($keys, JSON_PRETTY_PRINT)
        );
    }

    private function checkMissingTranslations($keys)
    {
        $locales = $this->resolveLocales();
        $missing = [];

        if (!$this->option('json')) {
            $this->info("Total translation keys found: " . count($keys));
        }

        // Pre-fetch rows that were explicitly ignored or fixed — we skip them in the report
        // so the GUI/CLI never re-propose keys the user has already triaged.
        $skipGlobal = [];     // key → true (when triaged with no specific locale)
        $skipPerLocale = [];  // key → [locale => true]

        if (!$this->option('include-triaged')) {
            $triagedRows = \Condoedge\Utils\Models\MissingTranslation::query()
                ->whereIn('translation_key', $keys)
                ->where(function ($q) {
                    $q->whereNotNull('ignored_at')->orWhereNotNull('fixed_at');
                })
                ->get(['translation_key', 'locale', 'ignored_at', 'fixed_at']);

            foreach ($triagedRows as $row) {
                if (empty($row->locale)) {
                    $skipGlobal[$row->translation_key] = true;
                } else {
                    $skipPerLocale[$row->translation_key][$row->locale] = true;
                }
            }
        }

        foreach ($locales as $locale) {
            if (!$this->option('json')) {
                $this->info("Checking translations for locale: {$locale}");
            }

            foreach ($keys as $key) {
                if (isset($skipGlobal[$key]) || isset($skipPerLocale[$key][$locale])) {
                    continue;
                }
                if (!$this->hasTranslation($key, $locale)) {
                    $locations = $this->keyFileMap[$key] ?? [];

                    $missingData = [
                        'key' => $key,
                        'locations' => $locations
                    ];
                    $missing[$locale][] = $missingData;
                    
                    // Save to MissingTranslation table with file locations
                    try {
                        $firstLocation = !empty($locations) ? $locations[0] : null;
                        $filename = $firstLocation['file'] ?? null;

                        if ($filename && str_contains($filename, 'MissingTranslationAnalyzerCommand')) {
                            $filename = null;
                        }

                        \Condoedge\Utils\Models\MissingTranslation::upsertMissingTranslation(
                            $key,
                            $filename,
                            $locale,
                            $filename
                        );
                    } catch (\Exception $e) {
                        // Silently continue if database save fails
                    }
                }
            }
        }

        if ($this->option('json')) {
            $this->outputJson($missing);
        } else {
            $this->displayMissingTranslations($missing);

            // Ask if user wants to add some keys to exclusion list
            if (!empty($missing)) {
                $this->info("\nWant to add some keys to the exclusion list?");
                $this->info("You can edit the file: " . storage_path('app/translation_exclude_keys.json'));
            }
        }
    }
    private function resolveLocales(): array
    {
        $cli = (array) $this->option('locale');
        if (!empty($cli)) {
            return $cli;
        }

        $configured = config('app.supported_locales');
        if (is_array($configured) && !empty($configured)) {
            return $configured;
        }

        return ['en', 'fr'];
    }

    private function hasTranslation($key, $locale)
    {
        app()->setLocale($locale);

        if (!\Lang::has($key, $locale)) {
            return false;
        }

        // A key can be present with an empty or self-referencing value — still effectively untranslated.
        $value = \Lang::get($key, [], $locale);

        if (!is_string($value)) {
            return true;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || $trimmed === $key) {
            return false;
        }

        return true;
    }
   
    /**
     * Build all literal-capture regex patterns from the TRANSLATION_FUNCTIONS list + EXTRA_LITERAL_PATTERNS.
     */
    private function buildLiteralPatterns(): array
    {
        $patterns = [];

        foreach (self::TRANSLATION_FUNCTIONS as $fn) {
            $head = $fn['lookbehind'] . $fn['regex'];
            $closing = !empty($fn['multi_arg']) ? '[),]' : '\)';

            if (!empty($fn['is_raw_prefix'])) {
                // Raw-prefix entries already include the arg list before the key; no opening paren added.
                $patterns[$fn['regex']] = '/' . $head . '[\'"]([^\'"]+)[\'"]/u';
                continue;
            }

            // Standard positional form: func('key'...)
            $patterns[$fn['regex']] = '/' . $head . '\s*\(\s*[\'"]([^\'"]+)[\'"]\s*' . $closing . '/u';

            if (!empty($fn['named_arg'])) {
                // Named-arg form: func(key: 'key', ...)
                $patterns[$fn['regex'] . '__named'] = '/' . $head . '\s*\(\s*key:\s*[\'"]([^\'"]+)[\'"]\s*[,)]/u';
            }
        }

        foreach (self::EXTRA_LITERAL_PATTERNS as $name => $pattern) {
            $patterns[$name] = $pattern;
        }

        return $patterns;
    }

    /**
     * Build all dynamic-prefix extraction regex patterns from the TRANSLATION_FUNCTIONS list.
     */
    private function buildDynamicPatterns(): array
    {
        $patterns = [];

        foreach (self::TRANSLATION_FUNCTIONS as $fn) {
            if (!empty($fn['is_raw_prefix'])) {
                continue; // meaningless for throwValidationError-style (key is 2nd arg, rarely dynamic)
            }

            $head = $fn['lookbehind'] . $fn['regex'];

            // Interpolation form inside double-quoted: capture static head before $ or {$
            $patterns[] = '/' . $head . '\s*\(\s*["]([a-zA-Z][a-zA-Z0-9._-]*\.)[^"]*(?:\$|\{\$)/u';
            // Concatenation form: static head followed by dot-concat with a variable
            $patterns[] = '/' . $head . '\s*\(\s*[\'"]([a-zA-Z][a-zA-Z0-9._-]*\.)[\'"]\s*\./u';
        }

        return $patterns;
    }

    /**
     * Universal detection: every quoted literal that LOOKS like a translation key.
     *
     * Opt-in via --universal-detection. Scoped to UI files only (see isUiFile).
     * A "translation-looking" key is:
     *   - at least 2 chars long
     *   - starts with a letter
     *   - contains only [a-zA-Z0-9._-]
     *   - contains at least one '.' OR one '-' (otherwise it's likely a single common word)
     *
     * Routes config, class names, database column names are skipped because the scope
     * excludes routes/, config/, database/ from this pass.
     */
    /**
     * Requires at least one '.' — flat dash keys like "alert-triangle", "arrow-down" are too noisy
     * (icons, HTML IDs, CSS classes, HTTP headers). Flat keys are already captured by standard
     * regex when passed to helpers like _Html('accept-invitation').
     */
    private const UNIVERSAL_KEY_REGEX = '/[\'"]([a-z][a-zA-Z0-9_-]*\.[a-zA-Z0-9][a-zA-Z0-9._-]*[a-zA-Z0-9])[\'"]/u';

    /**
     * Paths that look like UI code (where translation calls are expected).
     * Used only when --universal-detection is enabled to keep false positives low.
     */
    private const UI_PATH_MARKERS = [
        '/Kompo/',
        '/Livewire/',
        '/Filament/',
        '/Components/',
        DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR,
    ];

    /**
     * Paths explicitly excluded from universal detection (non-UI concerns).
     */
    private const UNIVERSAL_EXCLUDE_PATHS = [
        DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'Jobs' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'Listeners' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'Observers' . DIRECTORY_SEPARATOR,
    ];

    private function isUiFile(string $realPath, string $content): bool
    {
        $normalized = str_replace('\\', '/', $realPath);
        $normalizedForExclude = $realPath;

        foreach (self::UNIVERSAL_EXCLUDE_PATHS as $excluded) {
            if (strpos($normalizedForExclude, $excluded) !== false) {
                return false;
            }
        }

        // Blade and Vue are always UI
        if (str_ends_with($normalized, '.blade.php') || str_ends_with($normalized, '.vue')) {
            return true;
        }

        foreach (self::UI_PATH_MARKERS as $marker) {
            if (strpos($normalized, str_replace('\\', '/', $marker)) !== false) {
                return true;
            }
        }

        // Enums with label() method often hold UI-facing literals
        if (preg_match('/enum\s+\w+[^{]*\{[^}]*function\s+label\s*\(/', $content)) {
            return true;
        }

        return false;
    }

    private function extractKeysFromContent($content, $filepath = '')
    {
        if ($this->shouldSkipContent($content)) {
            return [];
        }

        $keys = [];
        $lines = explode("\n", $content);
        $patterns = $this->buildLiteralPatterns();

        foreach ($patterns as $name => $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $key = trim($match[0]);
                    $offset = $match[1];

                    // Find line number from offset
                    $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;

                    // Get context (surrounding lines)
                    $contextStart = max(0, $lineNumber - 3);
                    $contextEnd = min(count($lines), $lineNumber + 2);
                    $contextLines = array_slice($lines, $contextStart, $contextEnd - $contextStart);
                    $context = implode("\n", $contextLines);

                    if ($this->isValidTranslationKey($key, $content, $key)) {
                        $keys[] = [
                            'key' => $key,
                            'line' => $lineNumber,
                            'context' => $context
                        ];
                    }
                }
            }
        }

        // Optional pass 2 — universal detection, UI-scoped.
        if ($this->option('universal-detection') && $filepath && $this->isUiFile($filepath, $content)) {
            if (preg_match_all(self::UNIVERSAL_KEY_REGEX, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $key = trim($match[0]);
                    $offset = $match[1];
                    $lineNumber = substr_count(substr($content, 0, $offset), "\n") + 1;
                    $contextStart = max(0, $lineNumber - 3);
                    $contextEnd = min(count($lines), $lineNumber + 2);
                    $contextLines = array_slice($lines, $contextStart, $contextEnd - $contextStart);
                    $context = implode("\n", $contextLines);

                    if ($this->isValidTranslationKey($key, $content, $key)) {
                        $keys[] = [
                            'key' => $key,
                            'line' => $lineNumber,
                            'context' => $context
                        ];
                    }
                }
            }
        }

        return $keys;
    }

    private function getMatchContext($content, $match)
    {
        // Narrow window BEFORE the match only: context-based exclusions are meant to catch
        // the immediate wrapping function call (e.g. `config('some.key')`), not unrelated nearby code.
        $position = strpos($content, $match);
        if ($position === false) {
            return '';
        }
        $start = max(0, $position - 40);
        $length = $position - $start;

        return substr($content, $start, $length);
    }

    private function isValidTranslationKey($key, $content = '', $matchedText = '')
    {
        $context = $matchedText ? $this->getMatchContext($content, $matchedText) : '';
        return $this->getKeyFilter()->isValidKey($key, $context);
    }

    private function getKeyFilter(): TranslationKeyFilter
    {
        if (!$this->keyFilter) {
            $this->keyFilter = new TranslationKeyFilter();
            $this->keyFilter->allowPlainText = (bool) $this->option('include-plain-text');
        }
        return $this->keyFilter;
    }
    
    private function shouldSkipFile($filename, $filepath)
    {
        $exclusions = self::FILE_EXCLUSIONS;

        // Check specific files
        if (in_array($filename, $exclusions['files'])) {
            return true;
        }

        // Check file patterns
        foreach ($exclusions['patterns'] as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        // Check paths
        foreach ($exclusions['paths'] as $path) {
            if (strpos($filepath, $path) !== false) {
                return true;
            }
        }

        return false;
    }
    
    private function shouldSkipContent($content)
    {
        $skipPatterns = [
            '/composer\.lock/', '/node_modules/', '/vendor\/\//',
            '/^\s*\/\*\*/', 
        ];
        
        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isExcludedKey($key)
    {
        // Load excluded keys from file if exists
        $excludedKeys = $this->getExcludedKeys();
        
        return in_array($key, $excludedKeys);
    }
    
    private function getExcludedKeys()
    {
        $excludeFile = storage_path('app/translation_exclude_keys.json');
        
        if (file_exists($excludeFile)) {
            $content = file_get_contents($excludeFile);
            return json_decode($content, true) ?: [];
        }

        // Default keys to exclude - common function names and framework-specific terms
        $defaultExcluded = [
            // Framework/Helper functions
            '_CollapsibleSideSection', '_CollapsibleInnerSection', '_CollapsibleSideTitle', '_CollapsibleSideItem',
            '_Button', '_Link', '_Flex', '_Html', '_Sax', '_Collapsible',
            
            // Common attributes/properties
            'class', 'id', 'href', 'src', 'alt', 'title', 'name', 'value', 'type',
            
            // Technical terms
            'php', 'js', 'css', 'html', 'json', 'xml', 'api', 'admin',
            
            // States/values
            'hidden', 'active', 'disabled', 'loading', 'home', 'login', 'logout'
        ];
        
        // Create exclusion file if it doesn't exist
        if (!file_exists($excludeFile)) {
            file_put_contents($excludeFile, json_encode($defaultExcluded, JSON_PRETTY_PRINT));
        }
        
        return $defaultExcluded;
    }
    
    private function addToExcludeList($keys)
    {
        $excludeFile = storage_path('app/translation_exclude_keys.json');
        $currentExcluded = $this->getExcludedKeys();
        
        $newExcluded = array_unique(array_merge($currentExcluded, (array)$keys));
        
        file_put_contents($excludeFile, json_encode($newExcluded, JSON_PRETTY_PRINT));
        
        $this->info("Added " . count((array)$keys) . " keys to exclusion list.");
    }
    
    private function showExcludedKeys()
    {
        $excluded = $this->getExcludedKeys();
        $this->info("Currently excluded keys (" . count($excluded) . "):");
        foreach ($excluded as $key) {
            $this->line("  - {$key}");
        }
    }
    
    private function resetExcludedKeys()
    {
        $excludeFile = storage_path('app/translation_exclude_keys.json');
        if (file_exists($excludeFile)) {
            unlink($excludeFile);
        }
        $this->info("Exclusion list reset to default values.");
    }
    
    private function outputJson($missing)
    {
        echo json_encode($missing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function displayMissingTranslations($missing)
    {
        foreach ($missing as $locale => $items) {
            if (!empty($items)) {
                $this->error("Missing translations for {$locale}:");
                foreach ($items as $item) {
                    $key = is_array($item) ? $item['key'] : $item;
                    $this->line("  - {$key}");
                }
                $this->line('');
            } else {
                $this->info("✓ All translations are complete for {$locale}");
            }
        }
    }

    /**
     * Detect dynamic translation calls and extract their literal prefix.
     *
     * Examples (backticks used in examples to avoid self-matching on this file):
     *   `__(<ns>.$var)`    →  extracts the static prefix before the interpolation
     *   `__(<ns>. . $var)` →  extracts the static prefix before the concatenation
     *
     * Pure variable calls like `__(<var>)` are deliberately IGNORED — they have no
     * static prefix so we can't derive any safe namespace to whitelist; those cases
     * remain a known blind spot.
     */
    private function collectDynamicPrefixes(string $content): void
    {
        foreach ($this->buildDynamicPatterns() as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $prefix) {
                    $this->dynamicPrefixes[$prefix] = true;
                }
            }
        }
    }

    private function hasDynamicPrefix(string $key): bool
    {
        foreach (array_keys($this->dynamicPrefixes) as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Load additional local package paths the user wants scanned for translation keys.
     * File: storage/app/translator_linked_packages.json (JSON array of absolute paths).
     */
    public static function loadLinkedPackages(): array
    {
        $path = storage_path('app/translator_linked_packages.json');
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode(file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter($decoded, fn($p) => is_string($p) && $p !== ''));
    }

    public static function saveLinkedPackages(array $paths): void
    {
        $path = storage_path('app/translator_linked_packages.json');
        @mkdir(dirname($path), 0755, true);
        file_put_contents(
            $path,
            json_encode(array_values(array_unique($paths)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    private function loadLocaleJson(string $locale): array
    {
        $path = resource_path("lang/{$locale}.json");
        if (!file_exists($path)) {
            return [];
        }
        return json_decode(file_get_contents($path), true) ?: [];
    }

    private function checkEmptyValues(): void
    {
        $locales = $this->resolveLocales();
        $report = [];

        foreach ($locales as $locale) {
            $data = $this->loadLocaleJson($locale);
            $empty = [];
            $selfRef = [];

            foreach ($data as $key => $value) {
                if (!is_string($value)) continue;
                $trimmed = trim($value);
                if ($trimmed === '') {
                    $empty[] = $key;
                } elseif ($trimmed === $key) {
                    $selfRef[] = $key;
                }
            }

            $report[$locale] = ['empty' => $empty, 'self_ref' => $selfRef];
        }

        if ($this->option('json')) {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }

        foreach ($report as $locale => $buckets) {
            $this->info("Locale [{$locale}]:");
            $this->line('  Empty values: ' . count($buckets['empty']));
            foreach ($buckets['empty'] as $k) $this->line("    - {$k}");
            $this->line('  Self-referencing (value === key): ' . count($buckets['self_ref']));
            foreach ($buckets['self_ref'] as $k) $this->line("    - {$k}");
            $this->line('');
        }
    }

    private function checkObsoleteKeys(array $usedKeys): void
    {
        $locales = $this->resolveLocales();
        $usedSet = array_flip($usedKeys);
        $report = [];

        // Build candidate lists per locale.
        $candidatesPerLocale = [];
        $allCandidates = [];
        $skippedByDynamicPrefix = 0;
        foreach ($locales as $locale) {
            $data = $this->loadLocaleJson($locale);
            $candidates = [];
            foreach (array_keys($data) as $key) {
                if (isset($usedSet[$key])) {
                    continue;
                }
                if ($this->hasDynamicPrefix($key)) {
                    $skippedByDynamicPrefix++;
                    continue;
                }
                $candidates[] = $key;
                $allCandidates[$key] = true;
            }
            $candidatesPerLocale[$locale] = $candidates;
        }

        // Second pass: literal-string grep across code. A key is "really obsolete"
        // only if nowhere in the codebase it appears as a quoted string.
        $reallyObsolete = $this->filterByLiteralGrep(array_keys($allCandidates));

        foreach ($candidatesPerLocale as $locale => $candidates) {
            $report[$locale] = array_values(array_intersect($candidates, $reallyObsolete));
        }

        if ($this->option('json')) {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }

        $rescued = count($allCandidates) - count($reallyObsolete);
        $prefixes = array_keys($this->dynamicPrefixes);
        sort($prefixes);
        $this->info("Dynamic prefixes detected (" . count($prefixes) . "): " . implode(', ', $prefixes));
        $this->info("Skipped {$skippedByDynamicPrefix} candidate keys matching dynamic prefixes.");
        $this->info("Literal-grep pass rescued {$rescued} more keys (found as quoted strings elsewhere in code).");
        $this->line('');

        foreach ($report as $locale => $keys) {
            $this->warn("Locale [{$locale}] — " . count($keys) . ' obsolete keys (present in JSON, never used in code):');
            foreach ($keys as $k) $this->line("  - {$k}");
            $this->line('');
        }
    }

    private function filterByLiteralGrep(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        // Read every scanned file ONCE into memory, then check each candidate via str_contains.
        $finder = new Finder();
        $files = $finder->files()
            ->in(base_path())
            ->name('*.php')->name('*.blade.php')->name('*.vue')->name('*.js')->name('*.ts')
            ->exclude('node_modules')->exclude('storage')->exclude('bootstrap/cache')->exclude('public')
            ->notName('*.lock')->notName('*.min.js')->notName('*.min.css')
            ->notPath('*/lang/*')->notPath('*/resources/lang/*')
            ->filter(function (\SplFileInfo $file) {
                $path = $file->getRealPath();
                if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) return true;
                if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'condoedge' . DIRECTORY_SEPARATOR) !== false) return true;
                if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'kompo' . DIRECTORY_SEPARATOR) !== false) return true;
                return false;
            });

        $haystack = '';
        foreach ($files as $file) {
            $haystack .= file_get_contents($file->getRealPath()) . "\n";
        }

        $reallyObsolete = [];
        foreach ($candidates as $key) {
            // Look for the key wrapped in single OR double quotes (literal string usage).
            if (
                strpos($haystack, "'" . $key . "'") === false
                && strpos($haystack, '"' . $key . '"') === false
            ) {
                $reallyObsolete[] = $key;
            }
        }

        return $reallyObsolete;
    }

    private function diffLocales(): void
    {
        $locales = $this->resolveLocales();
        $data = [];

        foreach ($locales as $locale) {
            $data[$locale] = array_keys($this->loadLocaleJson($locale));
        }

        $report = [];
        foreach ($locales as $locale) {
            $others = array_diff($locales, [$locale]);
            foreach ($others as $other) {
                $diffKey = "{$locale}_not_in_{$other}";
                $report[$diffKey] = array_values(array_diff($data[$locale], $data[$other]));
            }
        }

        if ($this->option('json')) {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }

        foreach ($report as $bucket => $keys) {
            $this->warn(str_replace('_', ' ', $bucket) . ' (' . count($keys) . '):');
            foreach ($keys as $k) $this->line("  - {$k}");
            $this->line('');
        }
    }

    private function mergeVendorTranslations()
    {
        $this->info("Merging vendor package translations...");

        $vendorPaths = [
            'vendor/condoedge',
            'vendor/kompo'
        ];

        $locales = ['en', 'fr'];
        $mergedTranslations = [];

        foreach ($locales as $locale) {
            $mergedTranslations[$locale] = [];
        }

        // Scan vendor directories for translation files
        $packageCount = 0;
        foreach ($vendorPaths as $vendorPath) {
            $fullPath = base_path($vendorPath);

            if (!is_dir($fullPath)) {
                $this->warn("Vendor path not found: {$vendorPath}");
                continue;
            }

            $packages = glob($fullPath . '/*', GLOB_ONLYDIR);

            foreach ($packages as $package) {
                $packageName = basename(dirname($package)) . '/' . basename($package);

                foreach ($locales as $locale) {
                    $translationFile = $package . '/resources/lang/' . $locale . '.json';

                    if (file_exists($translationFile)) {
                        $translations = json_decode(file_get_contents($translationFile), true);

                        if ($translations && is_array($translations)) {
                            $count = count($translations);
                            $this->line("  Found {$count} {$locale} translations in {$packageName}");
                            $mergedTranslations[$locale] = array_merge($mergedTranslations[$locale], $translations);
                            $packageCount++;
                        }
                    }
                }
            }
        }

        if ($packageCount === 0) {
            $this->warn("No vendor translations found.");
            return;
        }

        // Load existing project translations
        foreach ($locales as $locale) {
            $projectFile = resource_path("lang/{$locale}.json");
            $projectTranslations = [];

            if (file_exists($projectFile)) {
                $projectTranslations = json_decode(file_get_contents($projectFile), true) ?: [];
            }

            // Merge: project translations take priority over vendor
            $finalTranslations = array_merge($mergedTranslations[$locale], $projectTranslations);

            // Sort alphabetically
            ksort($finalTranslations);

            // Save back to project
            file_put_contents(
                $projectFile,
                json_encode($finalTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );

            $newCount = count($finalTranslations) - count($projectTranslations);
            $this->info("✓ Merged {$locale}.json - Added {$newCount} new translations, total: " . count($finalTranslations));
        }

        $this->info("\n✨ Vendor translations merged successfully!");
    }
}
