<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Console\GeneratorCommand;
use Illuminate\Support\Str;

class RequestMakeCommand extends GeneratorCommand
{
    protected $signature = 'module:make-request
                            {module : The name of the module}
                            {name : The name of the request class (e.g. StoreProductRequest)}
                            {--force : Overwrite the file if it already exists}';

    protected $description = 'Create a new FormRequest class inside Requests/ of a module';

    public function handle(): int
    {
        $module = $this->getModule();

        /** @var string $rawName */
        $rawName = $this->argument('name');
        [$className, $subNamespace, $relativeDir] = $this->parseClassInput($rawName);

        if (! Str::endsWith($className, 'Request')) {
            $className .= 'Request';
        }

        $subPath = $relativeDir !== '' ? $relativeDir.'/'.$className : $className;
        $filePath = $module->getPath("Requests/{$subPath}.php");

        $replacements = [
            'namespace' => rtrim($this->registry->getNamespace(), '\\'),
            'module' => $module->getName(),
            'class' => $className,
            'subNamespace' => $subNamespace !== '' ? '\\'.$subNamespace : '',
        ];

        $content = $this->replacePlaceholders($this->getStub('request'), $replacements);
        $force = (bool) $this->option('force');

        if ($this->writeFile($filePath, $content, $force)) {
            $this->components->info("Request [{$className}] created successfully at [{$filePath}].");

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
