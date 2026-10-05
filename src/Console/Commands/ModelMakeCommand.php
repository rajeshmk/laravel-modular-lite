<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Console\GeneratorCommand;
use Illuminate\Support\Str;

class ModelMakeCommand extends GeneratorCommand
{
    protected $signature = 'module:make-model
                            {module : The name of the module}
                            {name : The name of the model class (e.g. Product or Admin/Product)}
                            {--m|migration : Create a new migration file for the model}
                            {--c|controller : Create a new controller for the model}
                            {--r|request : Create form requests for the model}
                            {--f|factory : Create a new factory for the model}
                            {--s|seeder : Create a new seeder for the model}
                            {--a|all : Generate migration, factory, seeder, controller, and requests for the model}
                            {--force : Overwrite the file if it already exists}';

    protected $description = 'Create a new Eloquent Model class inside Models/ of a module';

    public function handle(): int
    {
        $module = $this->getModule();

        /** @var string $rawName */
        $rawName = $this->argument('name');
        [$className, $subNamespace, $relativeDir] = $this->parseClassInput($rawName);

        $subPath = $relativeDir !== '' ? $relativeDir.'/'.$className : $className;
        $filePath = $module->getPath("Models/{$subPath}.php");

        $replacements = [
            'namespace' => rtrim($this->registry->getNamespace(), '\\'),
            'module' => $module->getName(),
            'class' => $className,
            'subNamespace' => $subNamespace !== '' ? '\\'.$subNamespace : '',
        ];

        $content = $this->replacePlaceholders($this->getStub('model'), $replacements);
        $force = (bool) $this->option('force');

        if (! $this->writeFile($filePath, $content, $force)) {
            return self::FAILURE;
        }

        $this->components->info("Model [{$className}] created successfully at [{$filePath}].");

        $all = (bool) $this->option('all');

        // 1. Migration
        if ($all || (bool) $this->option('migration')) {
            $table = Str::snake(Str::pluralStudly($className));
            $this->call('module:make-migration', [
                'module' => $module->getName(),
                'name' => "create_{$table}_table",
                '--force' => $force,
            ]);
        }

        // 2. Factory
        if ($all || (bool) $this->option('factory')) {
            $factoryPath = $module->getPath("Database/Factories/{$subPath}Factory.php");
            $factoryReplacements = array_merge($replacements, [
                'class' => "{$className}Factory",
                'model' => $className,
            ]);
            $this->writeFile($factoryPath, $this->replacePlaceholders($this->getStub('factory'), $factoryReplacements), $force);
            $this->components->info("Factory [{$className}Factory] created successfully.");
        }

        // 3. Seeder
        if ($all || (bool) $this->option('seeder')) {
            $this->call('module:make-seeder', [
                'module' => $module->getName(),
                'name' => "{$className}Seeder",
                '--force' => $force,
            ]);
        }

        // 4. Controller
        if ($all || (bool) $this->option('controller')) {
            $this->call('module:make-controller', [
                'module' => $module->getName(),
                'name' => "{$className}Controller",
                '--api' => true,
                '--force' => $force,
            ]);
        }

        // 5. Request
        if ($all || (bool) $this->option('request')) {
            $this->call('module:make-request', [
                'module' => $module->getName(),
                'name' => "Store{$className}Request",
                '--force' => $force,
            ]);
            $this->call('module:make-request', [
                'module' => $module->getName(),
                'name' => "Update{$className}Request",
                '--force' => $force,
            ]);
        }

        return self::SUCCESS;
    }
}
