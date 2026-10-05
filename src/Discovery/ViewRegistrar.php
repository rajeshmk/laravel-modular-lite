<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Discovery;

use Hatchyu\ModularLite\Support\Module;
use Illuminate\View\Factory as ViewFactory;

final readonly class ViewRegistrar
{
    public function __construct(
        private ViewFactory $view
    ) {}

    public function register(ModuleRegistry $registry): void
    {
        /** @var Module $module */
        foreach ($registry->enabled() as $module) {
            if ($module->hasViews()) {
                $this->view->addNamespace($module->getSlug(), $module->getViewsPath());
            }
        }
    }
}
