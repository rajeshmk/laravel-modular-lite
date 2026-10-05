<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Hatchyu\ModularLite\Support\Module;
use Illuminate\Console\Command;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List all discovered lightweight modules and subsystem statuses';

    public function __construct(
        private readonly ModuleRegistry $registry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $modules = $this->registry->all();

        if ($modules->isEmpty()) {
            $this->components->info('No modules found.');

            return self::SUCCESS;
        }

        $rows = $modules->map(fn (Module $module): array => [
            'name' => $module->getName(),
            'slug' => $module->getSlug(),
            'routes' => ($module->hasWebRoutes() || $module->hasApiRoutes()) ? '<info>Yes</info>' : '<comment>No</comment>',
            'migrations' => $module->hasMigrations() ? '<info>Yes</info>' : '<comment>No</comment>',
            'models' => is_dir($module->getPath('Models')) ? '<info>Yes</info>' : '<comment>No</comment>',
            'controllers' => is_dir($module->getPath('Controllers')) ? '<info>Yes</info>' : '<comment>No</comment>',
            'provider' => $module->hasProvider() ? '<info>Yes</info>' : '<comment>No</comment>',
        ])->all();

        $this->table(['Module', 'Slug', 'Routes', 'Migrations', 'Models', 'Controllers', 'Provider'], $rows);

        return self::SUCCESS;
    }
}
