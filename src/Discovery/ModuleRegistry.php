<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Discovery;

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
