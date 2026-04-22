<?php

namespace Condoedge\Utils\Command;

use Condoedge\Utils\Models\MissingTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MarkMissingTranslationCommand extends Command
{
    protected $signature = 'app:mark-missing-translation
                            {keys* : One or more translation keys}
                            {--status=fixed : fixed | ignored | reset}
                            {--locale=* : Restrict to specific locales (default: all rows for the key)}';

    protected $description = 'Mark missing_translations rows as fixed or ignored (or reset) — used by the translator GUI.';

    public function handle(): int
    {
        $keys = (array) $this->argument('keys');
        $status = $this->option('status');
        $locales = (array) $this->option('locale');

        if (!in_array($status, ['fixed', 'ignored', 'reset'], true)) {
            $this->error("Invalid --status: {$status}. Use fixed, ignored, or reset.");
            return Command::INVALID;
        }

        $query = MissingTranslation::query()->whereIn('translation_key', $keys);
        if (!empty($locales)) {
            $query->whereIn('locale', $locales);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->warn('No matching rows.');
            return Command::SUCCESS;
        }

        foreach ($rows as $row) {
            match ($status) {
                'fixed'   => $row->fixed_at = now(),
                'ignored' => $row->ignored_at = now(),
                'reset'   => [$row->fixed_at = null, $row->ignored_at = null],
            };
            $row->save(); // triggers the observer that clears the TrackingTranslator cache
        }

        // Also clear any cache entries (belt & braces — the model observer does this
        // but some wildcard entries may persist from the legacy single-key layout).
        foreach ($keys as $key) {
            Cache::forget('translation_missing_' . $key);
            Cache::forget('translation_missing_' . $key . ':*');
            foreach ($locales ?: [''] as $locale) {
                Cache::forget('translation_missing_' . $key . ':' . ($locale ?: '*'));
            }
        }

        $this->info("Marked {$rows->count()} row(s) as {$status}.");
        return Command::SUCCESS;
    }
}
