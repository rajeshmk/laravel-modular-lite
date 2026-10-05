<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Hatchyu\ModularLite\Support\Module;
use Illuminate\Console\Command;

class ModuleSeedCommand extends Command
{
    protected $signature = 'module:seed
                            {module? : Specific module to seed}
                            {--class= : Specific seeder class to execute}
                            {--database= : The database connection to seed}
                            {--force : Force the operation to run in production}';

    protected $description = 'Run database seeders across all lightweight modules or for a specific module';

    public function __construct(
        private readonly ModuleRegistry $registry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var string|null $targetModule */
        $targetModule = $this->argument('module');

        $modules = $targetModule !== null && $targetModule !== ''
            ? collect([$this->registry->find($targetModule)])->filter()
            : $this->registry->all();

        if ($modules->isEmpty()) {
            if ($targetModule !== null) {
                $this->components->error("Module [{$targetModule}] not found.");

                return self::FAILURE;
            }

            $this->components->warn('No modules detected.');

            return self::SUCCESS;
        }

        $options = [];
        if ($this->option('database')) {
            $options['--database'] = $this->option('database');
        }
        if ($this->option('force')) {
            $options['--force'] = true;
        }

        /** @var Module $module */
        foreach ($modules as $module) {
            $seederClass = (string) ($this->option('class') ?? $module->getNamespace("Database\\Seeders\\{$module->getName()}DatabaseSeeder"));

            $seederPath = $module->getPath('Database/Seeders/'.class_basename($seederClass).'.php');
            if (file_exists($seederPath)) {
                require_once $seederPath;
            }

            if (class_exists($seederClass)) {
                $this->components->task("Seeding module [{$module->getName()}]", function () use ($seederClass, $options): bool {
                    $params = array_merge($options, ['--class' => $seederClass]);

                    return $this->callSilent('db:seed', $params) === 0;
                });
            }
        }

        $this->components->info('Module database seeding completed.');

        return self::SUCCESS;
    }
}
