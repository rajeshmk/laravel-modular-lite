<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Discovery;

use Hatchyu\ModularLite\Support\Module;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;

final readonly class CommandRegistrar
{
    public function __construct(
        private Application $app
    ) {}

    public function register(ModuleRegistry $registry): void
    {
        $commandClasses = [];

        /** @var Module $module */
        foreach ($registry->enabled() as $module) {
            $classes = $module->getCommandClasses();
            if (! empty($classes)) {
                $commandClasses = array_merge($commandClasses, $classes);
            }
        }

        if (! empty($commandClasses)) {
            Artisan::starting(function (Artisan $artisan) use ($commandClasses): void {
                $artisan->resolveCommands($commandClasses);
            });
        }
    }
}
