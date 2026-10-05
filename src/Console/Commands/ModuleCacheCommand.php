<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ModuleCacheCommand extends Command
{
    protected $signature = 'module:cache';

    protected $description = 'Compile discovery manifest for zero runtime filesystem scanning';

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->registry->flush();
        $manifest = $this->registry->toCacheArray();
        $cachePath = $this->registry->getCachePath();

        $this->files->ensureDirectoryExists(dirname($cachePath));

        $content = '<?php return '.var_export($manifest, true).';'.PHP_EOL;

        $this->files->put($cachePath, $content);

        $this->components->info('Module discovery cache compiled successfully.');
        $this->components->twoColumnDetail('Cached Modules', (string) count($manifest));
        $this->components->twoColumnDetail('Cache File', $cachePath);

        return self::SUCCESS;
    }
}
