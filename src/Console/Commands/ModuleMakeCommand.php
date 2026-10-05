<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Console\GeneratorCommand;
use Hatchyu\ModularLite\Support\Module;
use Illuminate\Support\Str;

class ModuleMakeCommand extends GeneratorCommand
{
    protected $signature = 'module:make
                            {name : The name of the module (e.g. Product, Blog, Order)}
                            {--force : Overwrite existing module files}';

    protected $description = 'Scaffold a new lightweight module with clean Laravel-native structure';

    public function handle(): int
    {
        /** @var string $rawName */
        $rawName = $this->argument('name');

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $rawName) !== 1) {
            $this->components->error("Invalid module name [{$rawName}]. Module names must begin with a letter and contain only alphanumeric characters, underscores, or hyphens.");

            return self::FAILURE;
        }

        $moduleName = Str::studly($rawName);
        $force = (bool) $this->option('force');

        $modulePath = $this->registry->getModulesPath().DIRECTORY_SEPARATOR.$moduleName;
        $module = new Module(
            name: $moduleName,
            path: $modulePath,
            namespace: $this->registry->getNamespace()
        );

        if (is_dir($modulePath) && ! $force) {
            $this->components->error("Module [{$moduleName}] already exists at [{$modulePath}]. Use --force to overwrite.");

            return self::FAILURE;
        }

        $this->components->info("Scaffolding lightweight module [{$moduleName}]...");

        $directories = [
            'Controllers',
            'Models',
            'Requests',
            'Resources',
            'Services',
            'Database/Migrations',
            'Database/Factories',
            'Database/Seeders',
            'Routes',
            'Views',
            'Providers',
            'Tests/Feature',
        ];

        foreach ($directories as $dir) {
            $this->ensureDirectoryExists($module->getPath($dir));
        }

        $replacements = [
            'namespace' => rtrim($this->registry->getNamespace(), '\\'),
            'module' => $moduleName,
            'slug' => $module->getSlug(),
        ];

        // 1. Service Provider
        $providerContent = $this->replacePlaceholders($this->getStub('provider'), $replacements);
        $this->writeFile($module->getPath("Providers/{$moduleName}ServiceProvider.php"), $providerContent, $force);

        // 2. Web routes
        $webRouteContent = $this->replacePlaceholders($this->getStub('routes.web'), $replacements);
        $this->writeFile($module->getPath('Routes/web.php'), $webRouteContent, $force);

        // 3. API routes
        $apiRouteContent = $this->replacePlaceholders($this->getStub('routes.api'), $replacements);
        $this->writeFile($module->getPath('Routes/api.php'), $apiRouteContent, $force);

        // 4. Default View
        $viewContent = "<div>\n    <h1>Welcome to {$moduleName} Module</h1>\n</div>\n";
        $this->writeFile($module->getPath('Views/index.blade.php'), $viewContent, $force);

        // 5. Initial Database Seeder
        $seederContent = $this->replacePlaceholders($this->getStub('seeder.database'), $replacements);
        $this->writeFile($module->getPath("Database/Seeders/{$moduleName}DatabaseSeeder.php"), $seederContent, $force);

        // 6. Gitkeep empty directories
        $emptyDirs = [
            'Controllers',
            'Models',
            'Requests',
            'Resources',
            'Services',
            'Database/Migrations',
            'Database/Factories',
            'Tests/Feature',
        ];

        foreach ($emptyDirs as $dir) {
            $gitkeepPath = $module->getPath("{$dir}/.gitkeep");
            if (! file_exists($gitkeepPath)) {
                touch($gitkeepPath);
            }
        }

        $this->registry->flush();

        $this->components->info("Module [{$moduleName}] successfully scaffolded at [{$modulePath}].");
        $this->components->bulletList([
            "Namespace: {$module->getNamespace()}",
            "Routes: {$module->getPath('Routes/web.php')}",
            "Provider: {$module->getNamespace('Providers\\'.$moduleName.'ServiceProvider')}",
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => [
                'What is the name of the module?',
                'e.g. Product, Blog, Order',
            ],
        ];
    }
}
