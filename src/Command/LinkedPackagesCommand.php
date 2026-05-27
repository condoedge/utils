<?php

namespace Condoedge\Utils\Command;

use Condoedge\Utils\Services\Translation\LocaleFilesRepository;
use Illuminate\Console\Command;

class LinkedPackagesCommand extends Command
{
    protected $signature = 'app:translator-packages
                            {action=list : list | add | remove | clear}
                            {path? : Filesystem path (required for add/remove)}';

    protected $description = 'Manage local package paths scanned by the translation analyzer.';

    public function handle(LocaleFilesRepository $repo): int
    {
        $action  = $this->argument('action');
        $path    = $this->argument('path');
        $current = $repo->linkedPackages();

        return match ($action) {
            'list'   => $this->listPackages($current),
            'add'    => $this->addPackage($repo, $current, $path),
            'remove' => $this->removePackage($repo, $current, $path),
            'clear'  => $this->clearPackages($repo),
            default  => $this->invalidAction($action),
        };
    }

    private function listPackages(array $current): int
    {
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
    }

    private function addPackage(LocaleFilesRepository $repo, array $current, ?string $path): int
    {
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
        $repo->saveLinkedPackages($current);
        $this->info("Linked: {$real}");
        return Command::SUCCESS;
    }

    private function removePackage(LocaleFilesRepository $repo, array $current, ?string $path): int
    {
        if (!$path) {
            $this->error('Provide a path: php artisan app:translator-packages remove /path/to/package');
            return Command::INVALID;
        }
        $target   = realpath($path) ?: $path;
        $filtered = array_values(array_filter(
            $current,
            fn($p) => $p !== $target && realpath($p) !== $target
        ));
        if (count($filtered) === count($current)) {
            $this->warn("Not found in linked list: {$path}");
            return Command::SUCCESS;
        }
        $repo->saveLinkedPackages($filtered);
        $this->info("Unlinked: {$target}");
        return Command::SUCCESS;
    }

    private function clearPackages(LocaleFilesRepository $repo): int
    {
        $repo->saveLinkedPackages([]);
        $this->info('Cleared all linked packages.');
        return Command::SUCCESS;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Unknown action: {$action}. Use list | add | remove | clear.");
        return Command::INVALID;
    }
}
