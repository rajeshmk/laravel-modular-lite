<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite;

use Hatchyu\ModularLite\Console\Commands\ControllerMakeCommand;
use Hatchyu\ModularLite\Console\Commands\CrudMakeCommand;
use Hatchyu\ModularLite\Console\Commands\MigrationMakeCommand;
use Hatchyu\ModularLite\Console\Commands\ModelMakeCommand;
use Hatchyu\ModularLite\Console\Commands\ModuleCacheCommand;
use Hatchyu\ModularLite\Console\Commands\ModuleClearCommand;
use Hatchyu\ModularLite\Console\Commands\ModuleDoctorCommand;
use Hatchyu\ModularLite\Console\Commands\ModuleListCommand;
use Hatchyu\ModularLite\Console\Commands\ModuleMakeCommand;
use Hatchyu\ModularLite\Console\Commands\ModuleRenameCommand;
use Hatchyu\ModularLite\Console\Commands\ModuleSeedCommand;
use Hatchyu\ModularLite\Console\Commands\RequestMakeCommand;
use Hatchyu\ModularLite\Console\Commands\ResourceMakeCommand;
use Hatchyu\ModularLite\Console\Commands\SeederMakeCommand;
use Hatchyu\ModularLite\Console\Commands\ServiceMakeCommand;
use Hatchyu\ModularLite\Console\Commands\TestMakeCommand;
use Hatchyu\ModularLite\Discovery\CommandRegistrar;
use Hatchyu\ModularLite\Discovery\ConfigRegistrar;
use Hatchyu\ModularLite\Discovery\FactoryGuesser;
use Hatchyu\ModularLite\Discovery\MigrationRegistrar;
use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Hatchyu\ModularLite\Discovery\PolicyGuesser;
use Hatchyu\ModularLite\Discovery\ProviderRegistrar;
use Hatchyu\ModularLite\Discovery\RouteRegistrar;
use Hatchyu\ModularLite\Discovery\ViewRegistrar;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

class ModularLiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/modular-lite.php', 'modular-lite');

        $this->app->singleton(ModuleRegistry::class, function ($app): ModuleRegistry {
            return new ModuleRegistry($app->make(ConfigRepository::class));
        });

        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var ModuleRegistry $registry */
        $registry = $this->app->make(ModuleRegistry::class);

        // Auto-discover Module Configs
        if ((bool) $config->get('modular-lite.autodiscover.configs', true)) {
            (new ConfigRegistrar($config, $this->app))->register($registry);
        }

        // Register Module Service Providers
        if ((bool) $config->get('modular-lite.autodiscover.providers', true)) {
            (new ProviderRegistrar($this->app))->register($registry);
        }
    }

    public function boot(): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);

        /** @var ModuleRegistry $registry */
        $registry = $this->app->make(ModuleRegistry::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/modular-lite.php' => config_path('modular-lite.php'),
            ], 'modular-lite-config');

            $this->publishes([
                __DIR__.'/stubs' => base_path('stubs/modular-lite'),
            ], 'modular-lite-stubs');

            $this->commands([
                ModuleMakeCommand::class,
                ModelMakeCommand::class,
                ControllerMakeCommand::class,
                RequestMakeCommand::class,
                ResourceMakeCommand::class,
                ServiceMakeCommand::class,
                MigrationMakeCommand::class,
                SeederMakeCommand::class,
                TestMakeCommand::class,
                CrudMakeCommand::class,
                ModuleRenameCommand::class,
                ModuleDoctorCommand::class,
                ModuleListCommand::class,
                ModuleCacheCommand::class,
                ModuleClearCommand::class,
                ModuleSeedCommand::class,
            ]);

            // Auto-discover Module Console Commands
            if ((bool) $config->get('modular-lite.autodiscover.commands', true)) {
                (new CommandRegistrar($this->app))->register($registry);
            }

            $this->optimizes(
                optimize: 'module:cache',
                clear: 'module:clear',
                key: 'modular-lite'
            );
        }

        // Auto-discover Model Factories
        if ((bool) $config->get('modular-lite.autodiscover.factories', true)) {
            (new FactoryGuesser)->register($registry->getNamespace());
        }

        // Auto-discover Authorization Policies
        if ((bool) $config->get('modular-lite.autodiscover.policies', true)) {
            (new PolicyGuesser)->register($registry->getNamespace());
        }

        // Auto-discover Module Routes
        if ((bool) $config->get('modular-lite.autodiscover.routes', true)) {
            (new RouteRegistrar($this->app->make('router'), $config, $this->app))->register($registry);
        }

        // Auto-discover Module Migrations
        if ((bool) $config->get('modular-lite.autodiscover.migrations', true)) {
            (new MigrationRegistrar($this->app))->register($registry);
        }

        // Auto-discover Module Views
        if ((bool) $config->get('modular-lite.autodiscover.views', true) && $this->app->bound('view')) {
            (new ViewRegistrar($this->app->make('view')))->register($registry);
        }
    }
}
