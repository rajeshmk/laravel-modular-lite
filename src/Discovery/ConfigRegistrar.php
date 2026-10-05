<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Discovery;

use Hatchyu\ModularLite\Support\Module;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;

final readonly class ConfigRegistrar
{
    public function __construct(
        private ConfigRepository $config,
        private Application $app
    ) {}

    public function register(ModuleRegistry $registry): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        /** @var Module $module */
        foreach ($registry->all() as $module) {
            if ($module->hasConfig()) {
                $configPath = $module->getConfigPath();
                if (file_exists($configPath)) {
                    $configValues = require $configPath;
                    if (is_array($configValues)) {
                        $slug = $module->getSlug();
                        $this->config->set($slug, array_merge(
                            (array) $this->config->get($slug, []),
                            $configValues
                        ));

                        $snake = Str::snake($module->getName());
                        if ($snake !== $slug) {
                            $this->config->set($snake, array_merge(
                                (array) $this->config->get($snake, []),
                                $configValues
                            ));
                        }
                    }
                }
            }
        }
    }
}
