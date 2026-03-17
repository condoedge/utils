<?php

namespace Condoedge\Utils\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLazyComponentsCommand extends Command
{
    protected $signature = 'kompo:clear-lazy';

    protected $description = 'Clear compiled lazy component closures';

    public function handle()
    {
        $path = storage_path('framework/kompo-lazy');

        if (is_dir($path)) {
            File::deleteDirectory($path);
            mkdir($path, 0755, true);
        }

        $this->info('Lazy component cache cleared.');

        return Command::SUCCESS;
    }
}
