<?php

namespace Condoedge\Utils\Command;

use Condoedge\Utils\Services\Translation\MissingTranslationRecord;
use Condoedge\Utils\Services\Translation\MissingTranslationsStore;
use Illuminate\Console\Command;

class MarkMissingTranslationCommand extends Command
{
    protected $signature = 'app:mark-missing-translation
                            {keys* : One or more translation keys}
                            {--status=fixed : fixed | ignored | reset}
                            {--locale=* : Restrict to specific locales (default: all rows for the key)}';

    protected $description = 'Mark missing_translations rows as fixed or ignored (or reset) — used by the translator GUI.';

    public function __construct(private readonly MissingTranslationsStore $store)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $keys    = (array) $this->argument('keys');
        $status  = $this->option('status');
        $locales = (array) $this->option('locale');

        if (!in_array($status, ['fixed', 'ignored', 'reset'], true)) {
            $this->error("Invalid --status: {$status}. Use fixed, ignored, or reset.");
            return Command::INVALID;
        }

        $matches = $this->store->query()
            ->whereIn('translation_key', $keys)
            ->when(!empty($locales), fn($q) => $q->whereIn('locale', $locales))
            ->get();

        if ($matches->isEmpty()) {
            $this->warn('No matching rows.');
            return Command::SUCCESS;
        }

        foreach ($matches as $row) {
            /** @var MissingTranslationRecord $row */
            match ($status) {
                'fixed'   => $this->store->markFixed($row->id),
                'ignored' => $this->store->markIgnored($row->id),
                'reset'   => $this->store->reset($row->id),
            };
        }

        $this->info("Marked {$matches->count()} row(s) as {$status}.");
        return Command::SUCCESS;
    }
}
