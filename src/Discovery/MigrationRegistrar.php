<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Discovery;

use Hatchyu\ModularLite\Support\Module;
use Illuminate\Contracts\Foundation\Application;

final readonly class MigrationRegistrar
{
    public function __construct(
        private Application $app
    ) {}

    public function register(ModuleRegistry $registry): void
    {
        /** @var Module $module */
        foreach ($registry->enabled() as $module) {
            if ($module->hasMigrations()) {
                $this->app->afterResolving('migrator', function ($migrator) use ($module) {
                    $migrator->path($module->getMigrationsPath());
                });
            }
        }
    }
}
