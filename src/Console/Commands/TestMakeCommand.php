<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Console\GeneratorCommand;
use Illuminate\Support\Str;

class TestMakeCommand extends GeneratorCommand
{
    protected $signature = 'module:make-test
                            {module : The name of the module}
                            {name : The name of the test (e.g. ProductTest)}
                            {--unit : Create a unit test instead of feature test}
                            {--force : Overwrite the file if it already exists}';

    protected $description = 'Create a new Pest test file inside Tests/ of a module';

    public function handle(): int
    {
        $module = $this->getModule();

        /** @var string $rawName */
        $rawName = $this->argument('name');
        [$className, $subNamespace, $relativeDir] = $this->parseClassInput($rawName);

        if (! Str::endsWith($className, 'Test')) {
            $className .= 'Test';
        }

        $type = (bool) $this->option('unit') ? 'Unit' : 'Feature';
        $subPath = $relativeDir !== '' ? "Tests/{$type}/".$relativeDir.'/'.$className : "Tests/{$type}/".$className;
        $filePath = $module->getPath("{$subPath}.php");

        $replacements = [
            'namespace' => rtrim($this->registry->getNamespace(), '\\'),
            'module' => $module->getName(),
            'class' => $className,
            'subNamespace' => $subNamespace !== '' ? '\\'.$subNamespace : '',
        ];

        $content = $this->replacePlaceholders($this->getStub('test.pest'), $replacements);
        $force = (bool) $this->option('force');

        if ($this->writeFile($filePath, $content, $force)) {
            $this->components->info("Test [{$className}] created successfully at [{$filePath}].");

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
