<?php

namespace Condoedge\Utils\Services\Translation;

/**
 * Walks the configured vendor directories, collects every translation they
 * ship (flat JSON + PHP-array lang files), and merges those entries into the
 * project-level locale JSON — giving precedence to project translations.
 *
 * Returns a structured report so the caller (a CLI command, typically) can
 * render it however it wants without mixing I/O and presentation here.
 */
class VendorTranslationMerger
{
    /**
     * @var string[]  base_path-relative defaults (scanned when no override passed).
     */
    private const DEFAULT_VENDOR_PATHS = [
        'vendor/condoedge',
        'vendor/kompo',
    ];

    public function __construct(
        private readonly LocaleFilesRepository $localeFiles,
        private readonly PhpArrayLangReader $phpArrayReader,
    ) {}

    /**
     * @param string[]|null $vendorPaths  null → {@see self::DEFAULT_VENDOR_PATHS}
     * @param string[]|null $locales      null → {@see LocaleFilesRepository::defaultLocales()}
     *
     * @return array{
     *   packageCount: int,
     *   missingPaths: string[],
     *   discoveries: array<int, array{package: string, locale: string, source: string, count: int}>,
     *   merges: array<string, array{added: int, total: int}>
     * }
     */
    public function merge(?array $vendorPaths = null, ?array $locales = null): array
    {
        $vendorPaths ??= self::DEFAULT_VENDOR_PATHS;
        $locales     ??= $this->localeFiles->defaultLocales();

        $mergedTranslations = array_fill_keys($locales, []);
        $discoveries        = [];
        $missingPaths       = [];
        $packageCount       = 0;

        foreach ($vendorPaths as $vendorPath) {
            $fullPath = base_path($vendorPath);
            if (!is_dir($fullPath)) {
                $missingPaths[] = $vendorPath;
                continue;
            }

            foreach (glob($fullPath . '/*', GLOB_ONLYDIR) ?: [] as $package) {
                $packageName = basename(dirname($package)) . '/' . basename($package);

                foreach ($locales as $locale) {
                    $collected = $this->collectPackageTranslations($package, $locale);
                    foreach ($collected as $source => $entries) {
                        if (empty($entries)) {
                            continue;
                        }
                        $discoveries[] = [
                            'package' => $packageName,
                            'locale'  => $locale,
                            'source'  => $source,
                            'count'   => count($entries),
                        ];
                        $mergedTranslations[$locale] = [...$mergedTranslations[$locale], ...$entries];
                        $packageCount++;
                    }
                }
            }
        }

        $merges = [];
        if ($packageCount > 0) {
            foreach ($locales as $locale) {
                $project = $this->localeFiles->load($locale);
                // Project translations win over vendor defaults.
                $final = [...$mergedTranslations[$locale], ...$project];
                ksort($final);
                $this->localeFiles->saveForLocale($locale, $final);

                $merges[$locale] = [
                    'added' => count($final) - count($project),
                    'total' => count($final),
                ];
            }
        }

        return [
            'packageCount' => $packageCount,
            'missingPaths' => $missingPaths,
            'discoveries'  => $discoveries,
            'merges'       => $merges,
        ];
    }

    /**
     * @return array{json: array<string, string>, php: array<string, string>}
     */
    private function collectPackageTranslations(string $package, string $locale): array
    {
        $out = ['json' => [], 'php' => []];

        $jsonFile = $package . '/resources/lang/' . $locale . '.json';
        if (file_exists($jsonFile)) {
            $decoded = json_decode(file_get_contents($jsonFile), true);
            if (is_array($decoded)) {
                $out['json'] = $decoded;
            }
        }

        $phpLangDir = $package . '/resources/lang/' . $locale;
        if (is_dir($phpLangDir) && glob($phpLangDir . '/*.php')) {
            $out['php'] = $this->phpArrayReader->flattenPackage($package, $locale);
        }

        return $out;
    }
}
