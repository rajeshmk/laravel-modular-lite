<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ModuleClearCommand extends Command
{
    protected $signature = 'module:clear';

    protected $aliases = ['module:clear-cache'];

    protected $description = 'Clear compiled module discovery cache manifest';

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cachePath = $this->registry->getCachePath();

        if ($this->files->exists($cachePath)) {
            $this->files->delete($cachePath);
            $this->registry->flush();
            $this->components->info('Module discovery cache cleared successfully.');
        } else {
            $this->components->info('No module discovery cache file found.');
        }

        return self::SUCCESS;
    }
}
