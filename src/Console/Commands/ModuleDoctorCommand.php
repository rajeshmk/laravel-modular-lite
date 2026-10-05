<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Hatchyu\ModularLite\Support\Module;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use ParseError;

class ModuleDoctorCommand extends Command
{
    protected $signature = 'module:doctor
                            {module? : Optional module name to inspect}
                            {--fix : Attempt automatic safe repairs for missing directories and files}
                            {--strict : Return a failure exit code if warnings or errors are found}';

    protected $description = 'Health diagnostic and self-healing repair tool for lightweight modules';

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->components->info('🩺 Running Laravel Modular Lite Health Doctor...');
        $hasIssues = false;
        $fixedCount = 0;
        $isFix = (bool) $this->option('fix');

        // 1. Check Modules Directory
        $modulesPath = $this->registry->getModulesPath();
        if (! $this->files->isDirectory($modulesPath)) {
            $this->components->error("Modules directory missing at [{$modulesPath}].");
            $hasIssues = true;

            if ($isFix) {
                $this->files->makeDirectory($modulesPath, 0o755, true);
                $this->components->info("Repaired: Created modules directory at [{$modulesPath}].");
                $fixedCount++;
            }
        } else {
            $this->components->twoColumnDetail('Modules Base Path', "<info>{$modulesPath}</info>");
        }

        // 2. Check Composer PSR-4 Mapping
        $composerPath = base_path('composer.json');
        $expectedNamespace = $this->registry->getNamespace();
        if ($this->files->exists($composerPath)) {
            /** @var array<string, mixed>|null $composerData */
            $composerData = json_decode((string) $this->files->get($composerPath), true);
            $psr4 = array_merge(
                (array) ($composerData['autoload']['psr-4'] ?? []),
                (array) ($composerData['autoload-dev']['psr-4'] ?? [])
            );

            if (isset($psr4[$expectedNamespace])) {
                $this->components->twoColumnDetail('Composer PSR-4 Mapping', "<info>{$expectedNamespace} => {$psr4[$expectedNamespace]}</info>");
            } else {
                $this->components->warn("Missing PSR-4 mapping [\"{$expectedNamespace}\": \"modules/\"] in composer.json.");
                $hasIssues = true;
            }
        }

        // 3. Check Cache Synchronization
        $isCached = $this->registry->isCached();
        if ($isCached) {
            $cachedCount = count($this->registry->all());
            $this->registry->flush();
            $fsCount = count($this->registry->all());

            if ($cachedCount !== $fsCount) {
                $this->components->warn("Module cache is out of sync (Cached: {$cachedCount}, Filesystem: {$fsCount}).");
                $hasIssues = true;

                if ($isFix) {
                    $this->callSilent('module:cache');
                    $this->components->info('Repaired: Rebuilt module discovery cache.');
                    $fixedCount++;
                }
            } else {
                $this->components->twoColumnDetail('Manifest Cache', '<info>In Sync</info>');
            }
        } else {
            $this->components->twoColumnDetail('Manifest Cache', '<comment>Disabled (Runtime Discovery)</comment>');
        }

        // 4. Module Inspection
        /** @var string|null $targetModuleName */
        $targetModuleName = $this->argument('module');

        $modules = $targetModuleName !== null && $targetModuleName !== ''
            ? collect([$this->registry->find($targetModuleName)])->filter()
            : $this->registry->all();

        if ($modules->isEmpty()) {
            if ($targetModuleName !== null) {
                $this->components->error("Module [{$targetModuleName}] not found.");

                return self::FAILURE;
            }

            $this->components->warn('No modules detected. Run [php artisan module:make <Name>] to create one.');

            return self::SUCCESS;
        }

        $rows = [];
        $requiredDirs = ['Controllers', 'Models', 'Requests', 'Routes'];

        /** @var Module $module */
        foreach ($modules as $module) {
            $moduleIssues = 0;
            $missingDirs = [];

            foreach ($requiredDirs as $dir) {
                $dirPath = $module->getPath($dir);
                if (! $this->files->isDirectory($dirPath)) {
                    $missingDirs[] = $dir;
                    $moduleIssues++;

                    if ($isFix) {
                        $this->files->makeDirectory($dirPath, 0o755, true);
                        $fixedCount++;
                    }
                }
            }

            $providerExists = $module->hasProvider();
            $providerClass = $module->getProviderClass();
            $providerValid = $providerExists && class_exists($providerClass);

            if (! $providerExists) {
                $moduleIssues++;
            }

            $webRoutes = $module->hasWebRoutes();
            $apiRoutes = $module->hasApiRoutes();
            $routesSyntaxOk = true;

            foreach ([$module->getWebRoutesPath(), $module->getApiRoutesPath()] as $routeFile) {
                if ($this->files->exists($routeFile)) {
                    $content = (string) $this->files->get($routeFile);
                    if ($this->hasSyntaxErrors($content)) {
                        $routesSyntaxOk = false;
                        $moduleIssues++;
                    }
                } elseif ($isFix) {
                    $this->files->ensureDirectoryExists(dirname($routeFile));
                    $this->files->put($routeFile, "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\Support\Facades\Route;\n");
                    $fixedCount++;
                }
            }

            // Dependency Check
            $depIssues = [];
            if ($module->isEnabled()) {
                foreach ($module->getDependencies() as $depName) {
                    $depModule = $this->registry->find($depName);
                    if ($depModule === null) {
                        $depIssues[] = "Missing [{$depName}]";
                        $moduleIssues++;
                    } elseif ($depModule->isDisabled()) {
                        $depIssues[] = "Disabled [{$depName}]";
                        $moduleIssues++;
                    }
                }
            }

            if ($moduleIssues > 0) {
                $hasIssues = true;
            }

            $rows[] = [
                'module' => $module->getName(),
                'status' => $module->isEnabled() ? '<info>Enabled</info>' : '<comment>Disabled</comment>',
                'dirs' => empty($missingDirs) ? '<info>Complete</info>' : '<comment>Missing: '.implode(', ', $missingDirs).'</comment>',
                'provider' => $providerValid ? '<info>Active</info>' : ($providerExists ? '<comment>Unregistered</comment>' : '<error>Missing</error>'),
                'routes' => ($webRoutes && $apiRoutes && $routesSyntaxOk) ? '<info>OK</info>' : (! $routesSyntaxOk ? '<error>Syntax Error</error>' : '<comment>Partial</comment>'),
                'dependencies' => empty($depIssues) ? '<info>OK</info>' : '<error>'.implode(', ', $depIssues).'</error>',
                'health' => $moduleIssues === 0 ? '<info>Healthy</info>' : "<comment>{$moduleIssues} Warning(s)</comment>",
            ];
        }

        $this->newLine();
        $this->table(['Module', 'Status', 'Directories', 'Provider', 'Routes', 'Dependencies', 'Health Status'], $rows);

        if ($fixedCount > 0) {
            $this->components->info("Doctor auto-repaired [{$fixedCount}] item(s).");
            $this->registry->flush();
        }

        if ($hasIssues && (bool) $this->option('strict')) {
            $this->components->error('Doctor found health warnings or configuration issues in strict mode.');

            return self::FAILURE;
        }

        $this->components->info('Doctor inspection complete.');

        return self::SUCCESS;
    }

    private function hasSyntaxErrors(string $code): bool
    {
        try {
            token_get_all($code, TOKEN_PARSE);

            return false;
        } catch (ParseError) {
            return true;
        }
    }
}
