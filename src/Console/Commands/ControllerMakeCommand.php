<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Console\GeneratorCommand;
use Illuminate\Support\Str;

class ControllerMakeCommand extends GeneratorCommand
{
    protected $signature = 'module:make-controller
                            {module : The name of the module}
                            {name : The name of the controller class (e.g. ProductController or Api/ProductController)}
                            {--api : Create an API controller in Controllers/Api/}
                            {--resource : Create a full resource controller}
                            {--force : Overwrite the file if it already exists}';

    protected $description = 'Create a new Controller class inside Controllers/ of a module';

    public function handle(): int
    {
        $module = $this->getModule();

        /** @var string $rawName */
        $rawName = $this->argument('name');
        [$className, $subNamespace, $relativeDir] = $this->parseClassInput($rawName);

        if (! Str::endsWith($className, 'Controller')) {
            $className .= 'Controller';
        }

        $isApi = (bool) $this->option('api');
        $isResource = (bool) $this->option('resource');

        $stubName = 'controller';
        $baseDir = 'Controllers';

        if ($isApi) {
            $stubName = 'controller.api';
            $baseDir = 'Controllers/Api';
        } elseif ($isResource) {
            $stubName = 'controller.resource';
        }

        $subPath = $relativeDir !== '' ? $baseDir.'/'.$relativeDir.'/'.$className : $baseDir.'/'.$className;
        $filePath = $module->getPath("{$subPath}.php");

        $replacements = [
            'namespace' => rtrim($this->registry->getNamespace(), '\\'),
            'module' => $module->getName(),
            'class' => $className,
            'slug' => $module->getSlug(),
            'subNamespace' => $subNamespace !== '' ? '\\'.$subNamespace : '',
        ];

        $content = $this->replacePlaceholders($this->getStub($stubName), $replacements);
        $force = (bool) $this->option('force');

        if ($this->writeFile($filePath, $content, $force)) {
            $this->components->info("Controller [{$className}] created successfully at [{$filePath}].");

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
