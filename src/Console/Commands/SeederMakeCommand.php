<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Console\GeneratorCommand;
use Illuminate\Support\Str;

class SeederMakeCommand extends GeneratorCommand
{
    protected $signature = 'module:make-seeder
                            {module : The name of the module}
                            {name : The name of the seeder class (e.g. ProductSeeder)}
                            {--force : Overwrite the file if it already exists}';

    protected $description = 'Create a new Seeder class inside Database/Seeders of a module';

    public function handle(): int
    {
        $module = $this->getModule();

        /** @var string $rawName */
        $rawName = $this->argument('name');
        [$className, $subNamespace, $relativeDir] = $this->parseClassInput($rawName);

        if (! Str::endsWith($className, 'Seeder')) {
            $className .= 'Seeder';
        }

        $subPath = $relativeDir !== '' ? $relativeDir.'/'.$className : $className;
        $filePath = $module->getPath("Database/Seeders/{$subPath}.php");

        $replacements = [
            'namespace' => rtrim($this->registry->getNamespace(), '\\'),
            'module' => $module->getName(),
            'class' => $className,
            'subNamespace' => $subNamespace !== '' ? '\\'.$subNamespace : '',
        ];

        $content = $this->replacePlaceholders($this->getStub('seeder'), $replacements);
        $force = (bool) $this->option('force');

        if ($this->writeFile($filePath, $content, $force)) {
            $this->components->info("Seeder [{$className}] created successfully at [{$filePath}].");

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
