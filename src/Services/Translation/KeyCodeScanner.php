<?php

namespace Condoedge\Utils\Services\Translation;

use Symfony\Component\Finder\Finder;

/**
 * Scans PHP / Blade / Vue / JS code for translation keys.
 *
 * Owns the authoritative `TRANSLATION_FUNCTIONS` metadata — literal capture
 * patterns, dynamic-prefix patterns (via {@see DynamicPrefixDetector}) and
 * vendor-scope rules are all derived from it. Sharing the detector across
 * services keeps literal + dynamic detection in sync.
 *
 * Callers can also reuse {@see buildCodeFilesFinder()} to walk the same
 * scope of files with different logic (e.g. ObsoleteKeyDetector's literal
 * grep rescue pass).
 */
class KeyCodeScanner
{
    /**
     * Every callable that takes a translation key as first argument.
     * Literal capture, named-arg capture, and dynamic-prefix extraction are
     * all derived from this list so adding a new helper = 1 entry here.
     *
     *   regex          callable name (regex-ready, with escapes)
     *   named_arg      supports PHP 8 named args `func(key: '...')`
     *   lookbehind     extra negative lookbehind to avoid false opens
     *   multi_arg      key followed by other args (e.g. trans_choice)
     *   is_raw_prefix  pattern already includes the arg list before the key
     */
    public const TRANSLATION_FUNCTIONS = [
        ['regex' => '__',                                                     'named_arg' => true,  'lookbehind' => ''],
        ['regex' => 'trans',                                                  'named_arg' => true,  'lookbehind' => '(?<![a-zA-Z])'],
        ['regex' => 'trans_choice',                                           'named_arg' => true,  'lookbehind' => '',              'multi_arg' => true],
        ['regex' => '@lang',                                                  'named_arg' => false, 'lookbehind' => ''],
        ['regex' => 'Lang::get',                                              'named_arg' => false, 'lookbehind' => ''],
        ['regex' => '\$this->translator',                                     'named_arg' => false, 'lookbehind' => ''],
        ['regex' => '_[A-Z][a-zA-Z]+',                                        'named_arg' => false, 'lookbehind' => '', 'multi_arg' => true],
        ['regex' => '_',                                                      'named_arg' => false, 'lookbehind' => '(?<![a-zA-Z])'],
        ['regex' => '\$t',                                                    'named_arg' => false, 'lookbehind' => '(?<![a-zA-Z])'],
        ['regex' => 'i18n\.t',                                                'named_arg' => false, 'lookbehind' => '(?<![a-zA-Z])'],
        ['regex' => 'throwValidationError\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*',   'named_arg' => false, 'lookbehind' => '', 'is_raw_prefix' => true],
    ];

    /** Patterns that are NOT function calls (PHP properties, Vue directives, array values). */
    private const EXTRA_LITERAL_PATTERNS = [
        'titles'          => '/protected\s+\$_Title\s*=\s*[\'"](.+?)[\'"]\s*;/u',
        'vue_directive_t' => '/v-t\s*=\s*[\'"]([^\'"]+)[\'"]/u',
        'array_value'     => '/=>\s*[\'"]([a-z][a-z0-9_-]*\.[a-z][a-z0-9._-]*)[\'"]/iu',
    ];

    /**
     * When universal-detection is enabled, every quoted literal matching this
     * shape inside a UI file is considered a candidate key.
     */
    private const UNIVERSAL_KEY_REGEX = '/[\'"]([a-z][a-zA-Z0-9_-]*\.[a-zA-Z0-9][a-zA-Z0-9._-]*[a-zA-Z0-9])[\'"]/u';

    private const UI_PATH_MARKERS = [
        '/Kompo/',
        '/Livewire/',
        '/Filament/',
        '/Components/',
    ];

    private const UNIVERSAL_EXCLUDE_PATHS = [
        'routes', 'config', 'database', 'bootstrap',
        'Console', 'Exceptions', 'Middleware', 'Providers',
        'Jobs', 'Listeners', 'Observers',
    ];

    private const FILE_EXCLUSIONS = [
        'files' => [
            'composer.lock', 'package-lock.json', 'yarn.lock', 'webpack.mix.js',
            'tailwind.config.js', 'vite.config.js', '_ide_helper.php', 'server.php', 'artisan',
        ],
        'patterns' => [
            '/\.min\.(js|css)$/', '/\.lock$/', '/Test\.php$/', '/_test\.php$/',
            '/Migration\.php$/', '/Seeder\.php$/', '/Factory\.php$/',
        ],
        'paths' => [
            '/vendor/', '/node_modules/', '/storage/', '/bootstrap/cache/',
            '/public/build/', '/public/hot',
        ],
    ];

    private ?DynamicPrefixDetector $dynamicDetector = null;

    /** @var array<string, string>|null  memoized literal-capture patterns */
    private ?array $literalPatterns = null;

    public function __construct(
        private readonly TranslationKeyFilter $filter,
    ) {}

    /**
     * Full scan. Returns an indexed list of unique keys + a per-key location map.
     *
     * @param string[] $linkedPackages absolute paths of extra scan roots
     * @return array{keys: string[], locations: array<string, array<int, array{file:string, line:int, context:string}>>}
     */
    public function scan(array $linkedPackages = [], bool $universalDetection = false, bool $includePlainText = false): array
    {
        $this->filter->allowPlainText = $includePlainText;

        $finder = $this->buildCodeFilesFinder($linkedPackages);

        $keys      = [];
        $locations = [];

        foreach ($finder as $file) {
            $path     = $file->getRealPath();
            $filename = $file->getFilename();
            if ($this->shouldSkipFile($filename, $path)) {
                continue;
            }
            $content = file_get_contents($path);
            $this->dynamicDetector()->collect($content);

            foreach ($this->extractKeysFromContent($content, $path, $universalDetection) as $hit) {
                $key = $hit['key'];
                $keys[] = $key;

                $rel = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
                $locations[$key] ??= [];
                $locations[$key][] = [
                    'file'    => $rel,
                    'line'    => $hit['line'],
                    'context' => $hit['context'],
                ];
            }
        }

        return [
            'keys'      => array_values(array_unique($keys)),
            'locations' => $locations,
        ];
    }

    /**
     * Shared Finder used both by scan() and by the obsolete-detection
     * literal-grep rescue pass. Ensures both walk exactly the same file set.
     *
     * @param string[] $linkedPackages
     * @param string[] $linkedReal realpath-resolved copy of $linkedPackages
     */
    public function buildCodeFilesFinder(array $linkedPackages = [], ?array $linkedReal = null): Finder
    {
        $linkedReal ??= array_filter(array_map('realpath', $linkedPackages));

        $finder = (new Finder())
            ->files()
            ->in(base_path())
            ->name('*.php')->name('*.blade.php')->name('*.vue')->name('*.js')->name('*.ts')
            ->exclude('node_modules')
            ->exclude('storage')
            ->exclude('bootstrap/cache')
            ->exclude('public')
            ->notName('*.lock')->notName('*.min.js')->notName('*.min.css')
            ->notPath('*/migrations/*')
            ->notPath('*/seeders/*')
            ->notPath('*/factories/*')
            ->notPath('*/tests/*')
            ->notPath('*/Test*')
            ->notPath('*/_ide_helper*')
            ->notPath('*/config/cache/*')
            ->notPath('*/lang/*')
            ->notPath('*/resources/lang/*');

        foreach ($linkedPackages as $path) {
            if (is_dir($path)) {
                $finder->in($path);
            }
        }

        return $finder->filter(function (\SplFileInfo $file) use ($linkedReal) {
            $path = $file->getRealPath();

            foreach ($linkedReal as $linked) {
                if ($linked && strpos($path, $linked . DIRECTORY_SEPARATOR) === 0) {
                    return true;
                }
            }

            if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
                return true;
            }
            if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'condoedge' . DIRECTORY_SEPARATOR) !== false
                || strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'kompo' . DIRECTORY_SEPARATOR) !== false) {
                return true;
            }
            return false;
        });
    }

    public function dynamicDetector(): DynamicPrefixDetector
    {
        return $this->dynamicDetector ??= new DynamicPrefixDetector(self::TRANSLATION_FUNCTIONS);
    }

    // ------------------------------------------------------------------ internals

    /**
     * @return array<int, array{key:string, line:int, context:string}>
     */
    private function extractKeysFromContent(string $content, string $filepath, bool $universalDetection): array
    {
        if ($this->shouldSkipContent($content)) {
            return [];
        }

        $results  = [];
        $lines    = explode("\n", $content);
        $patterns = $this->literalPatterns ??= $this->buildLiteralPatterns();

        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[1] as $match) {
                $this->appendHit($results, $content, $lines, $match);
            }
        }

        if ($universalDetection && $this->isUiFile($filepath, $content)) {
            if (preg_match_all(self::UNIVERSAL_KEY_REGEX, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $this->appendHit($results, $content, $lines, $match);
                }
            }
        }

        return $results;
    }

    /**
     * @param array<int, array{key:string, line:int, context:string}> $results
     * @param array{0:string, 1:int} $match (PREG_OFFSET_CAPTURE pair)
     */
    private function appendHit(array &$results, string $content, array $lines, array $match): void
    {
        $key    = trim($match[0]);
        $offset = $match[1];
        if (!$this->filter->isValidKey($key, $this->getMatchContext($content, $key))) {
            return;
        }

        $lineNumber   = substr_count(substr($content, 0, $offset), "\n") + 1;
        $contextStart = max(0, $lineNumber - 3);
        $contextEnd   = min(count($lines), $lineNumber + 2);
        $snippet      = implode("\n", array_slice($lines, $contextStart, $contextEnd - $contextStart));

        $results[] = [
            'key'     => $key,
            'line'    => $lineNumber,
            'context' => $snippet,
        ];
    }

    /**
     * Build every literal-capture regex from TRANSLATION_FUNCTIONS + EXTRA_LITERAL_PATTERNS.
     * @return array<string, string>
     */
    private function buildLiteralPatterns(): array
    {
        $patterns = [];
        foreach (self::TRANSLATION_FUNCTIONS as $fn) {
            $head    = ($fn['lookbehind'] ?? '') . $fn['regex'];
            $closing = !empty($fn['multi_arg']) ? '[),]' : '\)';

            if (!empty($fn['is_raw_prefix'])) {
                $patterns[$fn['regex']] = '/' . $head . '[\'"]([^\'"]+)[\'"]/u';
                continue;
            }

            $patterns[$fn['regex']] = '/' . $head . '\s*\(\s*[\'"]([^\'"]+)[\'"]\s*' . $closing . '/u';

            if (!empty($fn['named_arg'])) {
                $patterns[$fn['regex'] . '__named'] = '/' . $head . '\s*\(\s*key:\s*[\'"]([^\'"]+)[\'"]\s*[,)]/u';
            }
        }

        foreach (self::EXTRA_LITERAL_PATTERNS as $name => $pattern) {
            $patterns[$name] = $pattern;
        }

        return $patterns;
    }

    private function getMatchContext(string $content, string $match): string
    {
        $position = strpos($content, $match);
        if ($position === false) {
            return '';
        }
        $start = max(0, $position - 40);
        return substr($content, $start, $position - $start);
    }

    private function shouldSkipFile(string $filename, string $filepath): bool
    {
        $exclusions = self::FILE_EXCLUSIONS;

        if (in_array($filename, $exclusions['files'], true)) {
            return true;
        }
        foreach ($exclusions['patterns'] as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }
        foreach ($exclusions['paths'] as $path) {
            if (strpos($filepath, $path) !== false) {
                return true;
            }
        }
        return false;
    }

    private function shouldSkipContent(string $content): bool
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

    private function isUiFile(string $realPath, string $content): bool
    {
        $normalized = str_replace('\\', '/', $realPath);

        foreach (self::UNIVERSAL_EXCLUDE_PATHS as $excluded) {
            if (strpos($normalized, '/' . $excluded . '/') !== false) {
                return false;
            }
        }

        if (str_ends_with($normalized, '.blade.php') || str_ends_with($normalized, '.vue')) {
            return true;
        }

        foreach (self::UI_PATH_MARKERS as $marker) {
            if (strpos($normalized, $marker) !== false) {
                return true;
            }
        }

        if (preg_match('/enum\s+\w+[^{]*\{[^}]*function\s+label\s*\(/', $content)) {
            return true;
        }

        return false;
    }
}
