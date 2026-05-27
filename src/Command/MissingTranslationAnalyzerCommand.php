<?php

namespace Condoedge\Utils\Command;

use Condoedge\Utils\Services\Translation\ExcludedKeysRepository;
use Condoedge\Utils\Services\Translation\KeyCodeScanner;
use Condoedge\Utils\Services\Translation\LocaleFilesRepository;
use Condoedge\Utils\Services\Translation\MissingTranslationCheckerInterface;
use Condoedge\Utils\Services\Translation\ObsoleteKeyDetector;
use Condoedge\Utils\Services\Translation\VendorTranslationMerger;
use Illuminate\Console\Command;

class MissingTranslationAnalyzerCommand extends Command
{
    /**
     * Options for the --json report used by the default "missing keys" run.
     * Kept UNESCAPED_SLASHES so file paths stay readable in the output.
     */
    private const MISSING_JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

    /**
     * Options for sub-reports (empty values, obsolete keys, locale diff).
     * Kept UNESCAPED_UNICODE so localized keys render verbatim.
     */
    private const SUBREPORT_JSON_OPTIONS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;

    /**
     * Per-key source locations populated by {@see extractAllTranslationKeys()}
     * and consumed by {@see checkMissingTranslations()} / the output helpers.
     *
     * @var array<string, array<int, array{file:string, line:int, context:string}>>
     */
    private array $keyFileMap = [];

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

    public function __construct(
        private readonly KeyCodeScanner $scanner,
        private readonly LocaleFilesRepository $localeFiles,
        private readonly MissingTranslationCheckerInterface $missingChecker,
        private readonly ExcludedKeysRepository $excludedKeys,
        private readonly ObsoleteKeyDetector $obsoleteDetector,
        private readonly VendorTranslationMerger $vendorMerger,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command. Options are mutually exclusive — the first
     * matching branch wins; the final arm is the default "check missing" flow.
     */
    public function handle(): void
    {
        match (true) {
            $this->option('show-excluded')          => $this->showExcludedKeys(),
            $this->option('reset-excluded')         => $this->resetExcludedKeys(),
            (bool) $this->option('exclude-key')     => $this->addToExcludeList((array) $this->option('exclude-key')),
            $this->option('merge-vendor')           => $this->mergeVendorTranslations(),
            $this->option('check-empty-values')     => $this->checkEmptyValues(),
            $this->option('check-obsolete')         => $this->checkObsoleteKeys($this->extractAllTranslationKeys()),
            $this->option('diff-locales')           => $this->diffLocales(),
            default                                 => $this->runDefaultMissingCheck(),
        };
    }

    private function runDefaultMissingCheck(): void
    {
        $keys = $this->extractAllTranslationKeys();
        $this->indexKeys($keys);
        $this->checkMissingTranslations($keys);
    }
    
    private function extractAllTranslationKeys(): array
    {
        $result = $this->scanner->scan(
            $this->localeFiles->linkedPackages(),
            (bool) $this->option('universal-detection'),
            (bool) $this->option('include-plain-text'),
        );
        $this->keyFileMap = $result['locations'];
        return $result['keys'];
    }

    private function indexKeys(array $keys): void
    {
        $this->info("Indexing " . count($keys) . " translation keys...");
        $this->localeFiles->indexKeys($keys);
    }

    private function checkMissingTranslations(array $keys): void
    {
        if (!$this->option('json')) {
            $this->info('Total translation keys found: ' . count($keys));
        }

        $locales = $this->resolveLocales();
        $missingByLocale = $this->missingChecker->find(
            $keys,
            $locales,
            (bool) $this->option('include-triaged')
        );

        $report = [];
        foreach ($missingByLocale as $locale => $missingKeys) {
            if (!$this->option('json')) {
                $this->info("Checking translations for locale: {$locale}");
            }
            foreach ($missingKeys as $key) {
                $locations = $this->keyFileMap[$key] ?? [];
                $report[$locale][] = ['key' => $key, 'locations' => $locations];
                $this->persistMissing($key, $locale, $locations);
            }
        }

        if ($this->option('json')) {
            $this->outputJson($report);
            return;
        }

        $this->displayMissingTranslations($report);
        if (!empty($report)) {
            $this->info("\nWant to add some keys to the exclusion list?");
            $this->info('You can edit the file: ' . storage_path('app/translation_exclude_keys.json'));
        }
    }

    private function persistMissing(string $key, string $locale, array $locations): void
    {
        try {
            $filename = $locations[0]['file'] ?? null;
            if ($filename && str_contains($filename, 'MissingTranslationAnalyzerCommand')) {
                $filename = null;
            }
            \Condoedge\Utils\Models\MissingTranslation::upsertMissingTranslation($key, $filename, $locale, $filename);
        } catch (\Throwable $e) {
            // Silently continue if DB save fails — JSON is the source of truth.
        }
    }

    private function resolveLocales(): array
    {
        return $this->localeFiles->resolveLocales((array) $this->option('locale'));
    }

    private function addToExcludeList(array $keys): void
    {
        $added = $this->excludedKeys->add($keys);
        $this->info("Added {$added} key(s) to exclusion list.");
    }

    private function showExcludedKeys(): void
    {
        $excluded = $this->excludedKeys->all();
        $this->info('Currently excluded keys (' . count($excluded) . '):');
        foreach ($excluded as $key) {
            $this->line("  - {$key}");
        }
    }

    private function resetExcludedKeys(): void
    {
        $this->excludedKeys->reset();
        $this->info('Exclusion list reset to default values.');
    }
    
    private function outputJson(array $missing): void
    {
        echo json_encode($missing, self::MISSING_JSON_OPTIONS);
    }

    private function displayMissingTranslations(array $missing): void
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

    private function checkEmptyValues(): void
    {
        $report = $this->localeFiles->emptyValuesReport($this->resolveLocales());

        if ($this->option('json')) {
            echo json_encode($report, self::SUBREPORT_JSON_OPTIONS);
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
        $result = $this->obsoleteDetector->detect($usedKeys, $this->resolveLocales());
        $report = $result['report'];

        if ($this->option('json')) {
            echo json_encode($report, self::SUBREPORT_JSON_OPTIONS);
            return;
        }

        $this->info("Dynamic prefixes detected (" . count($result['prefixes']) . "): " . implode(', ', $result['prefixes']));
        $this->info("Skipped {$result['skippedByDynamicPrefix']} candidate keys matching dynamic prefixes.");
        $this->info("Literal-grep pass rescued {$result['rescued']} more keys (found as quoted strings elsewhere in code).");
        $this->line('');

        foreach ($report as $locale => $keys) {
            $this->warn("Locale [{$locale}] — " . count($keys) . ' obsolete keys (present in JSON, never used in code):');
            foreach ($keys as $k) $this->line("  - {$k}");
            $this->line('');
        }
    }

    private function diffLocales(): void
    {
        $report = $this->localeFiles->diffLocalesReport($this->resolveLocales());

        if ($this->option('json')) {
            echo json_encode($report, self::SUBREPORT_JSON_OPTIONS);
            return;
        }

        foreach ($report as $bucket => $keys) {
            $this->warn(str_replace('_', ' ', $bucket) . ' (' . count($keys) . '):');
            foreach ($keys as $k) $this->line("  - {$k}");
            $this->line('');
        }
    }

    private function mergeVendorTranslations(): void
    {
        $this->info("Merging vendor package translations...");

        $report = $this->vendorMerger->merge();

        foreach ($report['missingPaths'] as $missing) {
            $this->warn("Vendor path not found: {$missing}");
        }

        foreach ($report['discoveries'] as $d) {
            $label = $d['source'] === 'json' ? 'JSON' : 'PHP-array';
            $this->line("  Found {$d['count']} {$d['locale']} {$label} translations in {$d['package']}");
        }

        if ($report['packageCount'] === 0) {
            $this->warn("No vendor translations found.");
            return;
        }

        foreach ($report['merges'] as $locale => $stats) {
            $this->info("✓ Merged {$locale}.json - Added {$stats['added']} new translations, total: {$stats['total']}");
        }

        $this->info("\n✨ Vendor translations merged successfully!");
    }
}
