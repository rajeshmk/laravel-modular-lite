<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Discovery;

use Hatchyu\ModularLite\Exceptions\ModuleDependencyException;
use Hatchyu\ModularLite\Support\Module;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ModuleRegistry
{
    /**
     * @var Collection<int, Module>|null
     */
    private ?Collection $modules = null;

    public function __construct(
        private readonly ConfigRepository $config
    ) {}

    public function flush(): self
    {
        $this->modules = null;

        return $this;
    }

    /**
     * @return Collection<int, Module>
     */
    public function all(): Collection
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $cachePath = $this->getCachePath();

        if (file_exists($cachePath)) {
            /** @var array<int, array<string, mixed>> $cached */
            $cached = require $cachePath;
            $this->modules = collect($cached)->map(
                fn (array $data): Module => new Module(
                    name: $data['name'],
                    path: $data['path'],
                    namespace: $data['namespace'],
                    cachedData: $data
                )
            );

            return $this->modules;
        }

        $this->modules = $this->discover();

        return $this->modules;
    }

    /**
     * Return only enabled modules, sorted topologically by dependency order.
     *
     * @return Collection<string, Module>
     *
     * @throws ModuleDependencyException
     */
    public function enabled(): Collection
    {
        $enabled = $this->all()->filter(fn (Module $module): bool => $module->isEnabled());

        return $this->sortTopologically($enabled);
    }

    /**
     * Return only disabled modules.
     *
     * @return Collection<string, Module>
     */
    public function disabled(): Collection
    {
        return $this->all()
            ->filter(fn (Module $module): bool => $module->isDisabled())
            ->keyBy(fn (Module $m): string => $m->getName());
    }

    /**
     * Sort enabled modules in dependency order, validating missing, disabled, and circular dependencies.
     *
     * @param  Collection<int, Module>  $modules
     * @return Collection<string, Module>
     *
     * @throws ModuleDependencyException
     */
    public function sortTopologically(Collection $modules): Collection
    {
        /** @var array<string, Module> $moduleMap */
        $moduleMap = [];
        foreach ($this->all() as $module) {
            $moduleMap[$module->getName()] = $module;
            $moduleMap[strtolower($module->getName())] = $module;
            $moduleMap[$module->getSlug()] = $module;
        }

        // Validate dependencies of all enabled modules
        foreach ($modules as $module) {
            foreach ($module->getDependencies() as $depName) {
                $depModule = $moduleMap[$depName] ?? $moduleMap[strtolower($depName)] ?? null;

                if ($depModule === null) {
                    throw ModuleDependencyException::missingDependency($module->getName(), $depName);
                }

                if ($depModule->isDisabled()) {
                    throw ModuleDependencyException::disabledDependency($module->getName(), $depModule->getName());
                }
            }
        }

        /** @var array<string, array<int, string>> $graph */
        $graph = [];
        /** @var array<string, int> $inDegree */
        $inDegree = [];

        foreach ($modules as $module) {
            $name = $module->getName();
            $graph[$name] = [];
            $inDegree[$name] = 0;
        }

        foreach ($modules as $module) {
            $name = $module->getName();
            foreach ($module->getDependencies() as $depName) {
                $depModule = $moduleMap[$depName] ?? $moduleMap[strtolower($depName)] ?? null;
                if ($depModule !== null && $depModule->isEnabled()) {
                    $depCanonical = $depModule->getName();
                    $graph[$depCanonical][] = $name;
                    $inDegree[$name]++;
                }
            }
        }

        $queue = [];
        foreach ($modules as $module) {
            $name = $module->getName();
            if ($inDegree[$name] === 0) {
                $queue[] = $name;
            }
        }

        $sortedNames = [];
        while (! empty($queue)) {
            $current = array_shift($queue);
            $sortedNames[] = $current;

            foreach ($graph[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sortedNames) !== $modules->count()) {
            $cycleNodes = array_diff($modules->pluck('name')->all(), $sortedNames);
            throw ModuleDependencyException::circularDependency(implode(' -> ', $cycleNodes));
        }

        return collect($sortedNames)->mapWithKeys(fn (string $name): array => [$name => $moduleMap[$name]]);
    }

    public function find(string $name): ?Module
    {
        $studly = Str::studly($name);
        $kebab = Str::kebab($name);

        return $this->all()->first(
            fn (Module $module): bool => strcasecmp($module->getName(), $name) === 0
                || strcasecmp($module->getSlug(), $name) === 0
                || strcasecmp($module->getName(), $studly) === 0
                || strcasecmp($module->getSlug(), $kebab) === 0
        );
    }

    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    public function isCached(): bool
    {
        return file_exists($this->getCachePath());
    }

    public function getCachePath(): string
    {
        /* @var string $path */
        return $this->config->get('modular-lite.cache_path', base_path('bootstrap/cache/modules-lite.php'));
    }

    public function getModulesPath(): string
    {
        /* @var string $path */
        return $this->config->get('modular-lite.path', base_path('modules'));
    }

    public function getNamespace(): string
    {
        /* @var string $namespace */
        return $this->config->get('modular-lite.namespace', 'Modules\\');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toCacheArray(): array
    {
        return $this->discover()->map(
            fn (Module $module): array => $module->toArray()
        )->values()->all();
    }

    /**
     * @return Collection<int, Module>
     */
    private function discover(): Collection
    {
        $basePath = $this->getModulesPath();

        if (! is_dir($basePath)) {
            return collect();
        }

        $directories = glob($basePath.'/*', GLOB_ONLYDIR);

        if ($directories === false) {
            return collect();
        }

        /** @var array<int, string> $ignored */
        $ignored = (array) $this->config->get('modular-lite.ignore', ['node_modules', 'vendor', '.git']);

        return collect($directories)
            ->map(fn (string $dir): string => basename($dir))
            ->filter(fn (string $name): bool => ! Str::startsWith($name, '.')
                && ! in_array($name, $ignored, true)
                && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name) === 1
            )
            ->map(function (string $name) use ($basePath): Module {
                $dir = $basePath.DIRECTORY_SEPARATOR.$name;

                return new Module(
                    name: $name,
                    path: $dir,
                    namespace: $this->getNamespace()
                );
            })
            ->sortBy(fn (Module $m): string => $m->getName())
            ->values();
    }
}
