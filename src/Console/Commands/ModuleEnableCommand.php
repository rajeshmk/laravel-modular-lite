<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Console\Concerns\PromptsForMissingInput as PromptsTrait;
use Illuminate\Contracts\Console\PromptsForMissingInput;

class ModuleEnableCommand extends Command implements PromptsForMissingInput
{
    use PromptsTrait;

    protected $signature = 'module:enable
                            {module : The name or slug of the module to enable}
                            {--force : Force enabling even if dependencies are missing or disabled}';

    protected $description = 'Enable a specific domain module';

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

        if ($module->isEnabled()) {
            $this->components->warn("Module [{$module->getName()}] is already enabled.");

            return self::SUCCESS;
        }

        // Dependency validation
        $force = (bool) $this->option('force');
        $dependencies = $module->getDependencies();

        if (! empty($dependencies)) {
            $missing = [];
            $disabled = [];

            foreach ($dependencies as $depName) {
                $depModule = $this->registry->find($depName);
                if ($depModule === null) {
                    $missing[] = $depName;
                } elseif ($depModule->isDisabled()) {
                    $disabled[] = $depModule->getName();
                }
            }

            if (! empty($missing)) {
                $this->components->warn("Module [{$module->getName()}] requires missing module(s): ".implode(', ', $missing).'.');
                if (! $force) {
                    $this->components->error('Cannot enable module with missing dependencies. Use --force to override.');

                    return self::FAILURE;
                }
            }

            if (! empty($disabled)) {
                $this->components->warn("Module [{$module->getName()}] depends on disabled module(s): ".implode(', ', $disabled).'.');
                if (! $force) {
                    $this->components->error('Please enable prerequisite modules first or use --force to override.');

                    return self::FAILURE;
                }
            }
        }

        $module->setEnabled(true);
        $this->registry->flush();

        if ($this->registry->isCached()) {
            $this->callSilent('module:cache');
        }

        $this->components->info("Module [{$module->getName()}] successfully enabled.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'module' => [
                'Which module do you want to enable?',
                'e.g. '.($this->registry->disabled()->first()?->getName() ?? 'Order'),
            ],
        ];
    }
}
