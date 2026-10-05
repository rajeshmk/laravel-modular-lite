<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Discovery;

use Hatchyu\ModularLite\Support\Module;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;

final readonly class RouteRegistrar
{
    public function __construct(
        private Router $router,
        private ConfigRepository $config,
        private Application $app
    ) {}

    public function register(ModuleRegistry $registry): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $apiPrefix = (string) $this->config->get('modular-lite.api_prefix', 'api');

        /** @var Module $module */
        foreach ($registry->enabled() as $module) {
            if ($module->hasWebRoutes()) {
                $this->router
                    ->middleware('web')
                    ->group($module->getWebRoutesPath());
            }

            if ($module->hasApiRoutes()) {
                $this->router
                    ->middleware('api')
                    ->prefix($apiPrefix)
                    ->group($module->getApiRoutesPath());
            }
        }
    }
}
