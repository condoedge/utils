<?php

namespace Condoedge\Utils\Command;

use Illuminate\Console\Command;

class LinkedPackagesCommand extends Command
{
    protected $signature = 'app:translator-packages
                            {action=list : list | add | remove | clear}
                            {path? : Filesystem path (required for add/remove)}';

    protected $description = 'Manage local package paths scanned by the translation analyzer.';

    public function handle(): int
    {
        $action = $this->argument('action');
        $path = $this->argument('path');

        $current = MissingTranslationAnalyzerCommand::loadLinkedPackages();

        switch ($action) {
            case 'list':
                if (empty($current)) {
                    $this->info('No linked packages.');
                    return Command::SUCCESS;
                }
                $this->info('Linked packages:');
                foreach ($current as $p) {
                    $marker = is_dir($p) ? '✓' : '✗ (missing)';
                    $this->line("  {$marker}  {$p}");
                }
                return Command::SUCCESS;

            case 'add':
                if (!$path) {
                    $this->error('Provide a path: php artisan app:translator-packages add /path/to/package');
                    return Command::INVALID;
                }
                $real = realpath($path);
                if (!$real || !is_dir($real)) {
                    $this->error("Not a directory: {$path}");
                    return Command::INVALID;
                }
                $current[] = $real;
                MissingTranslationAnalyzerCommand::saveLinkedPackages($current);
                $this->info("Linked: {$real}");
                return Command::SUCCESS;

            case 'remove':
                if (!$path) {
                    $this->error('Provide a path: php artisan app:translator-packages remove /path/to/package');
                    return Command::INVALID;
                }
                $target = realpath($path) ?: $path;
                $filtered = array_values(array_filter($current, function ($p) use ($target) {
                    return $p !== $target && realpath($p) !== $target;
                }));
                if (count($filtered) === count($current)) {
                    $this->warn("Not found in linked list: {$path}");
                    return Command::SUCCESS;
                }
                MissingTranslationAnalyzerCommand::saveLinkedPackages($filtered);
                $this->info("Unlinked: {$target}");
                return Command::SUCCESS;

            case 'clear':
                MissingTranslationAnalyzerCommand::saveLinkedPackages([]);
                $this->info('Cleared all linked packages.');
                return Command::SUCCESS;

            default:
                $this->error("Unknown action: {$action}. Use list | add | remove | clear.");
                return Command::INVALID;
        }
    }
}
