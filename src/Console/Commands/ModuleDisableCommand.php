<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Console\Concerns\PromptsForMissingInput as PromptsTrait;
use Illuminate\Contracts\Console\PromptsForMissingInput;

class ModuleDisableCommand extends Command implements PromptsForMissingInput
{
    use PromptsTrait;

    protected $signature = 'module:disable
                            {module : The name or slug of the module to disable}
                            {--force : Force disabling even if other active modules depend on it}';

    protected $description = 'Disable a specific domain module';

    public function __construct(
        private readonly ModuleRegistry $registry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var string $name */
        $name = $this->argument('module');

        $module = $this->registry->find($name);

        if ($module === null) {
            $this->components->error("Module [{$name}] does not exist.");

            return self::FAILURE;
        }

        if ($module->isDisabled()) {
            $this->components->warn("Module [{$module->getName()}] is already disabled.");

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        // Check if other active modules depend on this module
        $dependentModules = [];
        foreach ($this->registry->all() as $otherModule) {
            if ($otherModule->getName() === $module->getName() || $otherModule->isDisabled()) {
                continue;
            }

            foreach ($otherModule->getDependencies() as $dep) {
                if (strcasecmp($dep, $module->getName()) === 0 || strcasecmp($dep, $module->getSlug()) === 0) {
                    $dependentModules[] = $otherModule->getName();
                    break;
                }
            }
        }

        if (! empty($dependentModules)) {
            $depList = implode(', ', $dependentModules);
            $this->components->warn("Active module(s) [{$depList}] depend on [{$module->getName()}].");

            if (! $force) {
                $this->components->error("Cannot disable module [{$module->getName()}] because active module [{$dependentModules[0]}] depends on it. Use --force to disable anyway.");

                return self::FAILURE;
            }
        }

        $module->setEnabled(false);
        $this->registry->flush();

        if ($this->registry->isCached()) {
            $this->callSilent('module:cache');
        }

        $this->components->info("Module [{$module->getName()}] successfully disabled.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'module' => [
                'Which module do you want to disable?',
                'e.g. '.($this->registry->enabled()->first()?->getName() ?? 'Order'),
            ],
        ];
    }
}
