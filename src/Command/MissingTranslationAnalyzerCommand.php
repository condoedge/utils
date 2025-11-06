<?php

namespace Condoedge\Utils\Command;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class MissingTranslationAnalyzerCommand extends Command
{
    /**
     * Map of translation keys to their file locations
     *
     * @var array
     */
    private $keyFileMap = [];

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
                        {--merge-vendor : Merge translations from vendor packages (condoedge/*, kompo/*) into project}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze and find missing translation keys in the application';

    /**
     * Translation function patterns to match
     */
    private const TRANSLATION_PATTERNS = [
        '__' => '/__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        'trans' => '/trans\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        '@lang' => '/@lang\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        'trans_choice' => '/trans_choice\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/',
        'Lang::get' => '/Lang::get\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        'custom_translator' => '/\$this->translator\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        'custom_helpers' => '/_[a-zA-Z]+\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
        'underscore' => '/(?<![a-zA-Z])_\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
    ];

    /**
     * Exclusion rules configuration
     */
    private const EXCLUSION_RULES = [
        'contexts' => [
            'config(', 'env(', '->get(', 'Config::', 'config/', 'config.',
            'rules(', 'validator(', 'validate(', 'Validator::', 'function_exists(',
            'class_exists(', 'method_exists(', 'interface_exists(', 'trait_exists(',
            'defined(', 'constant(', 'storage_path(', 'resource_path(',
        ],
        'config_patterns' => [
            'app.', 'database.', 'cache.', 'queue.', 'mail.', 'session.',
            'filesystems.', 'broadcasting.', 'auth.', 'services.', 'logging.',
            'view.', 'sanctum.', 'cors.',
        ],
        'validation_rules' => [
            'required', 'nullable', 'string', 'integer', 'numeric', 'boolean',
            'email', 'url', 'date', 'datetime', 'image', 'file', 'unique', 'exists',
            'min', 'max', 'between', 'size', 'regex', 'confirmed', 'sometimes'
        ],
        'validation_patterns' => [
            'min:', 'max:', 'between:', 'size:', 'digits:', 'mimes:', 'dimensions:',
            'regex:', 'unique:', 'exists:', 'required_if:', 'in:', 'not_in:'
        ],
        'db_fields' => [
            'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token',
            'password', 'uuid', 'slug', 'status', 'type', 'active', 'visible'
        ],
        'common_words' => [
            'yes', 'no', 'ok', 'true', 'false', 'null', 'get', 'set', 'add',
            'remove', 'delete', 'create', 'update', 'class', 'id', 'name', 'type'
        ],
        'code_patterns' => [
            '/^[A-Z_]+$/',           // CONSTANTS
            '/^[a-z]+[A-Z]/',        // camelCase
            '/^\$/',                 // variables
            '/^(function|class|return)/'
        ]
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

        $keys = $this->extractAllTranslationKeys();
        $this->indexKeys($keys);
        $this->checkMissingTranslations($keys);
    }
    
    private function extractAllTranslationKeys()
    {
        $keys = [];
        $this->keyFileMap = [];

        // Scan main project files
        $this->info("Scanning project files...");
        $this->scanDirectory(base_path(), $keys, [
            'vendor', 'node_modules', 'storage', 'bootstrap/cache'
        ]);

        // Scan vendor packages (condoedge/*, kompo/*)
        $vendorPaths = [
            'vendor/condoedge',
            'vendor/kompo'
        ];

        foreach ($vendorPaths as $vendorPath) {
            $fullPath = base_path($vendorPath);
            
            if (!is_dir($fullPath)) {
                continue;
            }

            $packages = glob($fullPath . '/*', GLOB_ONLYDIR);
            
            foreach ($packages as $package) {
                $packageName = basename(dirname($package)) . '/' . basename($package);
                $this->info("Scanning package: {$packageName}");
                
                // Don't exclude vendor within these specific packages
                $this->scanDirectory($package, $keys, [
                    'node_modules', 'tests', 'Test'
                ]);
            }
        }

        return array_unique($keys);
    }

    private function scanDirectory($baseDir, &$keys, $excludeDirs = [])
    {
        $finder = new Finder();
        $finderInstance = $finder->files()
            ->in($baseDir)
            ->name('*.php')
            ->name('*.blade.php')
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
            ->notPath('*/resources/lang/*')
            ->notPath('*/Command/*')
            ->notPath('*/Commands/*')
            ->notPath('*/Console/*');

        // Exclude specified directories
        foreach ($excludeDirs as $dir) {
            $finderInstance->exclude($dir);
        }

        foreach ($finderInstance as $file) {
            // Skip files that shouldn't contain translations
            if ($this->shouldSkipFile($file->getFilename(), $file->getRealPath())) {
                continue;
            }

            $content = file_get_contents($file->getRealPath());
            $fileKeys = $this->extractKeysFromContent($content, $file->getRealPath());

            foreach ($fileKeys as $keyData) {
                $key = $keyData['key'];
                $keys[] = $key;

                // Store file location and context for each key
                if (!isset($this->keyFileMap[$key])) {
                    $this->keyFileMap[$key] = [];
                }
                
                // Normalize path separators to forward slashes
                $relativePath = str_replace('\\', '/', str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath()));
                
                $this->keyFileMap[$key][] = [
                    'file' => $relativePath,
                    'line' => $keyData['line'],
                    'context' => $keyData['context']
                ];
            }
        }
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
        $locales = ['en', 'fr']; // Configure your languages
        $missing = [];

        if (!$this->option('json')) {
            $this->info("Total translation keys found: " . count($keys));
        }

        foreach ($locales as $locale) {
            if (!$this->option('json')) {
                $this->info("Checking translations for locale: {$locale}");
            }

            foreach ($keys as $key) {
                if (!$this->hasTranslation($key, $locale)) {
                    $locations = $this->keyFileMap[$key] ?? [];
                    
                    $missingData = [
                        'key' => $key,
                        'locations' => $locations
                    ];
                    $missing[$locale][] = $missingData;
                    
                    // Save to MissingTranslation table with file locations
                    try {
                        $bestLocation = $this->findBestLocation($locations);
                        
                        $missingTranslation = \Condoedge\Utils\Models\MissingTranslation::upsertMissingTranslation($key);
                        
                        if ($missingTranslation && $bestLocation) {
                            // Extract package name from file path
                            $file = $bestLocation['file'];
                            $package = $this->extractPackageFromPath($file);
                            
                            // Update with file and package info
                            $missingTranslation->update([
                                'file' => $file,
                                'package' => $package,
                                'line' => $bestLocation['line'] ?? null,
                            ]);
                        }
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

    private function findBestLocation($locations)
    {
        if (empty($locations)) {
            return null;
        }
        
        // Priority order:
        // 1. Files from app/ or resources/
        // 2. Files from vendor packages that are NOT commands
        // 3. Any other file
        
        $prioritized = [];
        $nonCommand = [];
        $fallback = [];
        
        foreach ($locations as $location) {
            $file = $location['file'];
            
            // Skip command files
            if (strpos($file, '/Command/') !== false || 
                strpos($file, '/Commands/') !== false || 
                strpos($file, '/Console/') !== false) {
                $fallback[] = $location;
                continue;
            }
            
            // Prioritize app and resources
            if (str_starts_with($file, 'app/') || str_starts_with($file, 'resources/')) {
                $prioritized[] = $location;
            } else {
                $nonCommand[] = $location;
            }
        }
        
        // Return in priority order
        if (!empty($prioritized)) {
            return $prioritized[0];
        }
        
        if (!empty($nonCommand)) {
            return $nonCommand[0];
        }
        
        return !empty($fallback) ? $fallback[0] : $locations[0];
    }

    private function extractPackageFromPath($filePath)
    {
        // Check if it's from a vendor package
        if (preg_match('#vendor/([^/]+/[^/]+)/#', $filePath, $matches)) {
            return $matches[1]; // e.g., "condoedge/utils"
        }
        
        // Check if it's from app directory
        if (str_starts_with($filePath, 'app/')) {
            return 'app';
        }
        
        // Check if it's from resources
        if (str_starts_with($filePath, 'resources/')) {
            return 'resources';
        }
        
        // Default to the first directory
        $parts = explode('/', $filePath);
        return $parts[0] ?? 'unknown';
    }

    private function hasTranslation($key, $locale)
    {
        // Verificar si existe la traducción
        app()->setLocale($locale);
        
        // Usar Lang::has() que es más eficiente
        return \Lang::has($key, $locale);
    }
   
    private function extractKeysFromContent($content, $filepath = '')
    {
        if ($this->shouldSkipContent($content)) {
            return [];
        }

        $keys = [];
        $lines = explode("\n", $content);

        foreach (self::TRANSLATION_PATTERNS as $name => $pattern) {
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

        return $keys;
    }

    private function getMatchContext($content, $match)
    {
        $position = strpos($content, $match);
        $start = max(0, $position - 100);
        $length = min(200, strlen($content) - $start);
        
        return substr($content, $start, $length);
    }

    private function isValidTranslationKey($key, $content = '', $matchedText = '')
    {
        // Quick basic validations
        if (empty($key) || strlen($key) < 2 || strlen($key) > 100 || 
            is_numeric($key) || strpos($key, '$') !== false ||
            filter_var($key, FILTER_VALIDATE_URL) || filter_var($key, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Check context if provided
        if ($matchedText && !$this->isValidInContext($key, $this->getMatchContext($content, $matchedText))) {
            return false;
        }

        // Check if excluded
        if ($this->isExcludedKey($key)) {
            return false;
        }

        // Check against exclusion rules
        return $this->passesExclusionRules($key);
    }

    private function passesExclusionRules($key)
    {
        $rules = self::EXCLUSION_RULES;

        // Config patterns
        foreach ($rules['config_patterns'] as $pattern) {
            if (str_starts_with($key, $pattern)) return false;
        }

        // Validation rules
        if (in_array($key, $rules['validation_rules'])) return false;

        // Validation patterns
        foreach ($rules['validation_patterns'] as $pattern) {
            if (str_starts_with($key, $pattern)) return false;
        }

        // Database fields
        if (in_array($key, $rules['db_fields'])) return false;

        // Common words
        if (in_array(strtolower($key), $rules['common_words'])) return false;

        // Code patterns
        foreach ($rules['code_patterns'] as $pattern) {
            if (preg_match($pattern, $key)) return false;
        }

        // Must follow translation key pattern
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9._-]*$/', $key)) return false;

        // Exclude paths/filenames
        if (strpos($key, '/') !== false || strpos($key, '\\') !== false) return false;

        // Exclude CSS selectors
        if (str_starts_with($key, '.') || str_starts_with($key, '#')) return false;

        return true;
    }

    private function isValidInContext($key, $context)
    {
        foreach (self::EXCLUSION_RULES['contexts'] as $contextPattern) {
            if (stripos($context, $contextPattern) !== false) {
                return false;
            }
        }
        return true;
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
            '/^\s*\/\*\*/', '/use\s+[A-Z][a-zA-Z\\\\]+;/'
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
