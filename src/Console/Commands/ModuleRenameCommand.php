<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Console\Concerns\PromptsForMissingInput as PromptsTrait;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleRenameCommand extends Command implements PromptsForMissingInput
{
    use PromptsTrait;

    protected $signature = 'module:rename
                            {module : The current name of the module}
                            {new_name : The new name for the module}
                            {--force : Overwrite destination directory if it exists}';

    protected $description = 'Safely rename a module, updating directories, namespaces, and references';

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var string $currentName */
        $currentName = $this->argument('module');

        $module = $this->registry->find($currentName);
        if ($module === null) {
            $this->components->error("Module [{$currentName}] not found.");

            return self::FAILURE;
        }

        /** @var string $rawNewName */
        $rawNewName = $this->argument('new_name');

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $rawNewName) !== 1) {
            $this->components->error("Invalid new module name [{$rawNewName}]. Names must start with a letter and contain only alphanumeric characters, underscores, or hyphens.");

            return self::FAILURE;
        }

        $oldStudly = $module->getName();
        $newStudly = Str::studly($rawNewName);

        if (strcasecmp($oldStudly, $newStudly) === 0) {
            $this->components->warn("Module is already named [{$oldStudly}].");

            return self::SUCCESS;
        }

        $oldSlug = $module->getSlug();
        $newSlug = Str::kebab($newStudly);
        $oldSnake = Str::snake($oldStudly);
        $newSnake = Str::snake($newStudly);

        $rootNamespace = rtrim($this->registry->getNamespace(), '\\');
        $oldNamespace = "{$rootNamespace}\\{$oldStudly}";
        $newNamespace = "{$rootNamespace}\\{$newStudly}";

        $modulesPath = $this->registry->getModulesPath();
        $sourcePath = $module->getPath();
        $targetPath = $modulesPath.DIRECTORY_SEPARATOR.$newStudly;

        $force = (bool) $this->option('force');

        if ($this->files->exists($targetPath)) {
            if (! $force) {
                $this->components->error("Target path [{$targetPath}] already exists. Use --force to overwrite.");

                return self::FAILURE;
            }

            $this->files->deleteDirectory($targetPath);
        }

        $this->components->info("Renaming module [{$oldStudly}] to [{$newStudly}]...");

        // 1. Move root directory
        $this->files->moveDirectory($sourcePath, $targetPath);

        // 2. Rename specific files containing module name
        $oldProvider = $targetPath.DIRECTORY_SEPARATOR."Providers/{$oldStudly}ServiceProvider.php";
        $newProvider = $targetPath.DIRECTORY_SEPARATOR."Providers/{$newStudly}ServiceProvider.php";
        if ($this->files->exists($oldProvider)) {
            $this->files->move($oldProvider, $newProvider);
        }

        $oldRootProvider = $targetPath.DIRECTORY_SEPARATOR."{$oldStudly}ServiceProvider.php";
        $newRootProvider = $targetPath.DIRECTORY_SEPARATOR."{$newStudly}ServiceProvider.php";
        if ($this->files->exists($oldRootProvider)) {
            $this->files->move($oldRootProvider, $newRootProvider);
        }

        $oldSeeder = $targetPath.DIRECTORY_SEPARATOR."Database/Seeders/{$oldStudly}DatabaseSeeder.php";
        $newSeeder = $targetPath.DIRECTORY_SEPARATOR."Database/Seeders/{$newStudly}DatabaseSeeder.php";
        if ($this->files->exists($oldSeeder)) {
            $this->files->move($oldSeeder, $newSeeder);
        }

        // 3. Scan and rewrite file contents inside target directory
        $allFiles = $this->files->allFiles($targetPath);
        $updatedFilesCount = 0;

        foreach ($allFiles as $file) {
            $path = $file->getRealPath();
            $extension = $file->getExtension();

            if (! in_array($extension, ['php', 'json', 'md', 'stub', 'blade'], true) && ! Str::endsWith($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = $this->files->get($path);
            $original = $content;

            $content = str_replace(
                [
                    "{$oldNamespace}\\",
                    addslashes($oldNamespace).'\\\\',
                    $oldNamespace,
                    "class {$oldStudly}ServiceProvider",
                    "class {$oldStudly}DatabaseSeeder",
                    "{$oldStudly}ServiceProvider::class",
                    "{$oldStudly}DatabaseSeeder::class",
                    "'{$oldSlug}::",
                    "\"{$oldSlug}::",
                    "'modules/{$oldSlug}'",
                    "\"modules/{$oldSlug}\"",
                    "'{$oldSlug}.'",
                    "'{$oldSnake}.'",
                ],
                [
                    "{$newNamespace}\\",
                    addslashes($newNamespace).'\\\\',
                    $newNamespace,
                    "class {$newStudly}ServiceProvider",
                    "class {$newStudly}DatabaseSeeder",
                    "{$newStudly}ServiceProvider::class",
                    "{$newStudly}DatabaseSeeder::class",
                    "'{$newSlug}::",
                    "\"{$newSlug}::",
                    "'modules/{$newSlug}'",
                    "\"modules/{$newSlug}\"",
                    "'{$newSlug}.'",
                    "'{$newSnake}.'",
                ],
                $content
            );

            if ($content !== $original) {
                $this->files->put($path, $content);
                $updatedFilesCount++;
            }
        }

        // 4. Update module.json in renamed module if exists
        $targetManifest = $targetPath.DIRECTORY_SEPARATOR.'module.json';
        if ($this->files->exists($targetManifest)) {
            $manifestData = json_decode((string) $this->files->get($targetManifest), true);
            if (is_array($manifestData)) {
                $manifestData['name'] = $newStudly;
                $this->files->put($targetManifest, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            }
        }

        // 5. Update dependencies referencing old module name in other modules
        $renamedDepCount = 0;
        if ($this->files->isDirectory($modulesPath)) {
            foreach ($this->files->directories($modulesPath) as $otherDir) {
                if ($otherDir === $targetPath) {
                    continue;
                }
                $otherManifest = $otherDir.DIRECTORY_SEPARATOR.'module.json';
                if ($this->files->exists($otherManifest)) {
                    $otherData = json_decode((string) $this->files->get($otherManifest), true);
                    if (is_array($otherData) && isset($otherData['dependencies']) && is_array($otherData['dependencies'])) {
                        $modified = false;
                        foreach ($otherData['dependencies'] as $idx => $dep) {
                            if ($dep === $oldStudly || $dep === $oldSlug) {
                                $otherData['dependencies'][$idx] = $newStudly;
                                $modified = true;
                            }
                        }
                        if ($modified) {
                            $this->files->put($otherManifest, json_encode($otherData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
                            $renamedDepCount++;
                        }
                    }
                }
            }
        }

        // 6. Invalidate registry cache
        $this->registry->flush();
        if ($this->registry->isCached()) {
            $this->callSilent('module:cache');
        }

        $this->components->info("Module successfully renamed to [{$newStudly}].");
        $this->components->twoColumnDetail('Source Path', $sourcePath);
        $this->components->twoColumnDetail('New Path', $targetPath);
        $this->components->twoColumnDetail('Files Refactored', "<info>{$updatedFilesCount}</info>");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'module' => [
                'Which module do you want to rename?',
                'e.g. '.($this->registry->all()->first()?->getName() ?? 'Product'),
            ],
            'new_name' => [
                'What is the new name for the module?',
                'e.g. Catalog',
            ],
        ];
    }
}
