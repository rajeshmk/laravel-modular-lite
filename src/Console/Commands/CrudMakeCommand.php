<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Console\GeneratorCommand;
use Illuminate\Support\Str;

class CrudMakeCommand extends GeneratorCommand
{
    protected $signature = 'module:make-crud
                            {module : The name of the module}
                            {name : The name of the entity / model (e.g. Product, Category)}
                            {--no-migration : Skip generating migration file}
                            {--no-routes : Skip appending api resource route}
                            {--force : Overwrite existing files}';

    protected $aliases = ['module:crud'];

    protected $description = 'Scaffold a complete Eloquent CRUD slice (Model, Migration, Factory, Requests, Resource, Controller, Routes, Test)';

    public function handle(): int
    {
        $module = $this->getModule();

        /** @var string $rawName */
        $rawName = $this->argument('name');
        [$entityName, $subNamespace, $relativeDir] = $this->parseClassInput($rawName);

        $force = (bool) $this->option('force');
        $moduleName = $module->getName();
        $namespace = rtrim($this->registry->getNamespace(), '\\');
        $slugPlural = Str::plural(Str::kebab($entityName));
        $slugSingle = Str::kebab($entityName);
        $tableName = Str::snake(Str::pluralStudly($entityName));

        $this->components->info("Scaffolding lightweight CRUD slice for [{$entityName}] in [{$moduleName}]...");

        $commonReplacements = [
            'namespace' => $namespace,
            'module' => $moduleName,
            'model' => $entityName,
            'class' => $entityName,
            'slug' => $slugSingle,
            'slugPlural' => $slugPlural,
            'subNamespace' => $subNamespace !== '' ? '\\'.$subNamespace : '',
        ];

        // 1. Model
        $modelPath = $module->getPath("Models/{$entityName}.php");
        $modelContent = $this->replacePlaceholders($this->getStub('model'), $commonReplacements);
        $this->writeFile($modelPath, $modelContent, $force);

        // 2. Migration
        if (! (bool) $this->option('no-migration')) {
            $this->callSilent('module:make-migration', [
                'module' => $moduleName,
                'name' => "create_{$tableName}_table",
                '--force' => $force,
            ]);
        }

        // 3. Factory
        $factoryPath = $module->getPath("Database/Factories/{$entityName}Factory.php");
        $factoryReplacements = array_merge($commonReplacements, [
            'class' => "{$entityName}Factory",
        ]);
        $factoryContent = $this->replacePlaceholders($this->getStub('factory'), $factoryReplacements);
        $this->writeFile($factoryPath, $factoryContent, $force);

        // 4. Requests (Store & Update)
        $storeReqPath = $module->getPath("Requests/Store{$entityName}Request.php");
        $storeReqReplacements = array_merge($commonReplacements, ['class' => "Store{$entityName}Request"]);
        $this->writeFile($storeReqPath, $this->replacePlaceholders($this->getStub('request'), $storeReqReplacements), $force);

        $updateReqPath = $module->getPath("Requests/Update{$entityName}Request.php");
        $updateReqReplacements = array_merge($commonReplacements, ['class' => "Update{$entityName}Request"]);
        $this->writeFile($updateReqPath, $this->replacePlaceholders($this->getStub('request'), $updateReqReplacements), $force);

        // 5. Resource
        $resourcePath = $module->getPath("Resources/{$entityName}Resource.php");
        $resourceReplacements = array_merge($commonReplacements, ['class' => "{$entityName}Resource"]);
        $this->writeFile($resourcePath, $this->replacePlaceholders($this->getStub('resource'), $resourceReplacements), $force);

        // 6. Controller (Pure Eloquent API Controller)
        $controllerPath = $module->getPath("Controllers/Api/{$entityName}Controller.php");
        $controllerReplacements = array_merge($commonReplacements, ['class' => "{$entityName}Controller"]);
        $this->writeFile($controllerPath, $this->replacePlaceholders($this->getStub('crud.controller'), $controllerReplacements), $force);

        // 7. Test
        $testPath = $module->getPath("Tests/Feature/{$entityName}ControllerTest.php");
        $testStub = "<?php\n\ndeclare(strict_types=1);\n\nit('can list {$slugPlural} via api', function () {\n    \$response = \$this->getJson('/api/{$slugPlural}');\n    \$response->assertOk();\n});\n";
        $this->writeFile($testPath, $testStub, $force);

        // 8. Append API Route
        if (! (bool) $this->option('no-routes')) {
            $apiRoutesPath = $module->getApiRoutesPath();
            if (file_exists($apiRoutesPath)) {
                $routesContent = file_get_contents($apiRoutesPath);
                $controllerFqcn = "\\{$namespace}\\{$moduleName}\\Controllers\\Api\\{$entityName}Controller::class";

                if ($routesContent !== false && ! Str::contains($routesContent, $controllerFqcn) && ! Str::contains($routesContent, "{$entityName}Controller")) {
                    $routeSnippet = "\nRoute::apiResource('{$slugPlural}', {$controllerFqcn});\n";
                    file_put_contents($apiRoutesPath, $routeSnippet, FILE_APPEND);
                    $this->components->twoColumnDetail('API Route Registered', "<info>api/{$slugPlural}</info>");
                }
            }
        }

        $this->components->info("Lightweight CRUD slice for [{$entityName}] created successfully!");
        $this->components->bulletList([
            "Model: Models/{$entityName}.php",
            "Requests: Store{$entityName}Request, Update{$entityName}Request",
            "Resource: Resources/{$entityName}Resource.php",
            "Controller: Controllers/Api/{$entityName}Controller.php",
            "Test: Tests/Feature/{$entityName}ControllerTest.php",
        ]);

        return self::SUCCESS;
    }
}
