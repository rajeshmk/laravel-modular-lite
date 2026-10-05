<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Support;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final readonly class Module
{
    /**
     * @param  array<string, mixed>|null  $cachedData
     */
    public function __construct(
        private string $name,
        private string $path,
        private string $namespace,
        private ?array $cachedData = null
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return Str::kebab($this->name);
    }

    public function getPath(?string $subPath = null): string
    {
        if ($subPath === null || $subPath === '') {
            return $this->path;
        }

        $cleanSubPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($subPath, '/\\'));
        $parts = explode(DIRECTORY_SEPARATOR, $cleanSubPath);
        $safeParts = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (! empty($safeParts)) {
                    array_pop($safeParts);
                }

                continue;
            }
            $safeParts[] = $part;
        }

        return rtrim($this->path, '/\\').(empty($safeParts) ? '' : DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $safeParts));
    }

    public function getControllersPath(?string $subPath = null): string
    {
        return $this->getPath('Controllers'.($subPath !== null && $subPath !== '' ? DIRECTORY_SEPARATOR.ltrim($subPath, '/\\') : ''));
    }

    public function getModelsPath(?string $subPath = null): string
    {
        return $this->getPath('Models'.($subPath !== null && $subPath !== '' ? DIRECTORY_SEPARATOR.ltrim($subPath, '/\\') : ''));
    }

    public function getRequestsPath(?string $subPath = null): string
    {
        return $this->getPath('Requests'.($subPath !== null && $subPath !== '' ? DIRECTORY_SEPARATOR.ltrim($subPath, '/\\') : ''));
    }

    public function getResourcesPath(?string $subPath = null): string
    {
        return $this->getPath('Resources'.($subPath !== null && $subPath !== '' ? DIRECTORY_SEPARATOR.ltrim($subPath, '/\\') : ''));
    }

    public function getServicesPath(?string $subPath = null): string
    {
        return $this->getPath('Services'.($subPath !== null && $subPath !== '' ? DIRECTORY_SEPARATOR.ltrim($subPath, '/\\') : ''));
    }

    public function getDatabasePath(?string $subPath = null): string
    {
        $base = is_dir($this->getPath('Database')) ? 'Database' : 'database';

        return $this->getPath($base.($subPath !== null && $subPath !== '' ? DIRECTORY_SEPARATOR.ltrim($subPath, '/\\') : ''));
    }

    public function getMigrationsPath(): string
    {
        if ($this->cachedData !== null && isset($this->cachedData['migrations_path'])) {
            return (string) $this->cachedData['migrations_path'];
        }

        $pascalPath = $this->getPath('Database/Migrations');
        if (is_dir($pascalPath)) {
            return $pascalPath;
        }

        return $this->getPath('database/migrations');
    }

    public function getFactoriesPath(): string
    {
        if ($this->cachedData !== null && isset($this->cachedData['factories_path'])) {
            return (string) $this->cachedData['factories_path'];
        }

        $pascalPath = $this->getPath('Database/Factories');
        if (is_dir($pascalPath)) {
            return $pascalPath;
        }

        return $this->getPath('database/factories');
    }

    public function getSeedersPath(): string
    {
        if ($this->cachedData !== null && isset($this->cachedData['seeders_path'])) {
            return (string) $this->cachedData['seeders_path'];
        }

        $pascalPath = $this->getPath('Database/Seeders');
        if (is_dir($pascalPath)) {
            return $pascalPath;
        }

        return $this->getPath('database/seeders');
    }

    public function getTestsPath(?string $subPath = null): string
    {
        $base = is_dir($this->getPath('Tests')) ? 'Tests' : 'tests';

        return $this->getPath($base.($subPath !== null && $subPath !== '' ? DIRECTORY_SEPARATOR.ltrim($subPath, '/\\') : ''));
    }

    public function getNamespace(?string $subNamespace = null): string
    {
        $cleanRoot = rtrim($this->namespace, '\\');

        $base = Str::endsWith($cleanRoot, '\\'.$this->name) || $cleanRoot === $this->name
            ? $cleanRoot
            : $cleanRoot.'\\'.$this->name;

        if ($subNamespace === null || $subNamespace === '') {
            return $base;
        }

        return $base.'\\'.ltrim($subNamespace, '\\');
    }

    public function hasProvider(): bool
    {
        if ($this->cachedData !== null) {
            return ($this->cachedData['provider'] ?? null) !== null;
        }

        return file_exists($this->getProviderPath());
    }

    public function getProviderPath(): string
    {
        $providersDir = $this->getPath("Providers/{$this->name}ServiceProvider.php");
        if (file_exists($providersDir)) {
            return $providersDir;
        }

        return $this->getPath("{$this->name}ServiceProvider.php");
    }

    public function getProviderClass(): string
    {
        if ($this->cachedData !== null && isset($this->cachedData['provider'])) {
            return (string) $this->cachedData['provider'];
        }

        if (file_exists($this->getPath("Providers/{$this->name}ServiceProvider.php"))) {
            return $this->getNamespace("Providers\\{$this->name}ServiceProvider");
        }

        return $this->getNamespace("{$this->name}ServiceProvider");
    }

    public function hasWebRoutes(): bool
    {
        if ($this->cachedData !== null) {
            return (bool) ($this->cachedData['has_web_routes'] ?? false);
        }

        return file_exists($this->getWebRoutesPath());
    }

    public function getWebRoutesPath(): string
    {
        if (file_exists($this->getPath('Routes/web.php'))) {
            return $this->getPath('Routes/web.php');
        }

        return $this->getPath('routes/web.php');
    }

    public function hasApiRoutes(): bool
    {
        if ($this->cachedData !== null) {
            return (bool) ($this->cachedData['has_api_routes'] ?? false);
        }

        return file_exists($this->getApiRoutesPath());
    }

    public function getApiRoutesPath(): string
    {
        if (file_exists($this->getPath('Routes/api.php'))) {
            return $this->getPath('Routes/api.php');
        }

        return $this->getPath('routes/api.php');
    }

    public function hasMigrations(): bool
    {
        if ($this->cachedData !== null) {
            return (bool) ($this->cachedData['has_migrations'] ?? false);
        }

        return is_dir($this->getMigrationsPath());
    }

    public function hasFactories(): bool
    {
        if ($this->cachedData !== null) {
            return (bool) ($this->cachedData['has_factories'] ?? false);
        }

        return is_dir($this->getFactoriesPath());
    }

    public function hasSeeders(): bool
    {
        if ($this->cachedData !== null) {
            return (bool) ($this->cachedData['has_seeders'] ?? false);
        }

        return is_dir($this->getSeedersPath());
    }

    public function hasViews(): bool
    {
        if ($this->cachedData !== null) {
            return (bool) ($this->cachedData['has_views'] ?? false);
        }

        return is_dir($this->getViewsPath());
    }

    public function getViewsPath(): string
    {
        if (is_dir($this->getPath('Views'))) {
            return $this->getPath('Views');
        }

        return $this->getPath('resources/views');
    }

    public function hasConfig(): bool
    {
        if ($this->cachedData !== null) {
            return (bool) ($this->cachedData['has_config'] ?? false);
        }

        return file_exists($this->getConfigPath());
    }

    public function getConfigPath(): string
    {
        if (file_exists($this->getPath('Config/config.php'))) {
            return $this->getPath('Config/config.php');
        }

        return $this->getPath('config/config.php');
    }

    public function hasTests(): bool
    {
        if ($this->cachedData !== null) {
            return (bool) ($this->cachedData['has_tests'] ?? false);
        }

        return is_dir($this->getTestsPath());
    }

    public function hasCommands(): bool
    {
        if ($this->cachedData !== null) {
            return (bool) ($this->cachedData['has_commands'] ?? false);
        }

        return is_dir($this->getCommandsPath());
    }

    public function getCommandsPath(): string
    {
        if (is_dir($this->getPath('Console/Commands'))) {
            return $this->getPath('Console/Commands');
        }

        return $this->getPath('Commands');
    }

    /**
     * @return array<int, string>
     */
    public function getCommandClasses(): array
    {
        if ($this->cachedData !== null && isset($this->cachedData['command_classes'])) {
            /* @var array<int, string> */
            return (array) $this->cachedData['command_classes'];
        }

        if (! $this->hasCommands()) {
            return [];
        }

        $commandsPath = $this->getCommandsPath();
        if (! is_dir($commandsPath)) {
            return [];
        }

        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($commandsPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $pathName = $file->getPathname();
                if (file_exists($pathName)) {
                    require_once $pathName;
                }

                $relativePath = Str::after($pathName, rtrim($commandsPath, '/\\').DIRECTORY_SEPARATOR);
                $classPath = str_replace(['/', '\\'], '\\', Str::beforeLast($relativePath, '.php'));

                $relativeNamespace = Str::contains($commandsPath, 'Console/Commands')
                    ? 'Console\\Commands\\'
                    : 'Commands\\';

                $fullClass = $this->getNamespace($relativeNamespace.$classPath);

                if (class_exists($fullClass)) {
                    $reflection = new ReflectionClass($fullClass);
                    if ($reflection->isSubclassOf(Command::class) && ! $reflection->isAbstract()) {
                        $classes[] = $fullClass;
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->getSlug(),
            'path' => $this->path,
            'namespace' => $this->namespace,
            'module_namespace' => $this->getNamespace(),
            'provider' => $this->hasProvider() ? $this->getProviderClass() : null,
            'has_web_routes' => $this->hasWebRoutes(),
            'has_api_routes' => $this->hasApiRoutes(),
            'has_migrations' => $this->hasMigrations(),
            'migrations_path' => $this->hasMigrations() ? $this->getMigrationsPath() : null,
            'has_factories' => $this->hasFactories(),
            'factories_path' => $this->hasFactories() ? $this->getFactoriesPath() : null,
            'has_seeders' => $this->hasSeeders(),
            'seeders_path' => $this->hasSeeders() ? $this->getSeedersPath() : null,
            'has_views' => $this->hasViews(),
            'views_path' => $this->hasViews() ? $this->getViewsPath() : null,
            'has_config' => $this->hasConfig(),
            'config_path' => $this->hasConfig() ? $this->getConfigPath() : null,
            'has_tests' => $this->hasTests(),
            'has_commands' => $this->hasCommands(),
            'commands_path' => $this->hasCommands() ? $this->getCommandsPath() : null,
            'command_classes' => $this->getCommandClasses(),
        ];
    }
}
