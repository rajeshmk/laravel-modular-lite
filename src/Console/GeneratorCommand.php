<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console;

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Hatchyu\ModularLite\Support\Module;
use Illuminate\Console\Command;
use Illuminate\Console\Concerns\PromptsForMissingInput as PromptsTrait;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;

abstract class GeneratorCommand extends Command implements PromptsForMissingInput
{
    use PromptsTrait;

    public function __construct(
        protected readonly ModuleRegistry $registry
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'module' => [
                'Which module does this belong to?',
                'e.g. '.($this->registry->all()->first()?->getName() ?? 'Product'),
            ],
            'name' => [
                'What should this class be named?',
                'e.g. '.class_basename($this::class),
            ],
        ];
    }

    protected function getModule(): Module
    {
        /** @var string $name */
        $name = $this->argument('module');

        $module = $this->registry->find($name);

        if ($module !== null) {
            return $module;
        }

        // Return a virtual module instance pointing to expected location
        return new Module(
            name: Str::studly($name),
            path: $this->registry->getModulesPath().DIRECTORY_SEPARATOR.Str::studly($name),
            namespace: $this->registry->getNamespace()
        );
    }

    /**
     * Parse class input and split into class name, sub-namespace, and relative directory path.
     * Prevents path traversal vulnerabilities by validating and sanitizing each segment.
     *
     * @return array{0: string, 1: string, 2: string} [className, subNamespace, relativeDir]
     */
    protected function parseClassInput(string $input): array
    {
        $normalized = str_replace(['/', '\\'], '/', trim($input, '/\\'));
        $parts = explode('/', $normalized);

        $cleanParts = [];
        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '' || $trimmed === '.' || $trimmed === '..') {
                continue;
            }

            $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $trimmed);
            if ($sanitized !== null && $sanitized !== '') {
                $cleanParts[] = $sanitized;
            }
        }

        if (empty($cleanParts)) {
            $cleanParts = ['Item'];
        }

        $rawClass = array_pop($cleanParts);
        $className = Str::studly((string) $rawClass);

        $studlyParts = array_map([Str::class, 'studly'], $cleanParts);
        $subNamespace = implode('\\', $studlyParts);
        $relativeDir = implode(DIRECTORY_SEPARATOR, $studlyParts);

        return [$className, $subNamespace, $relativeDir];
    }

    protected function getStub(string $stubName): string
    {
        /** @var string|null $customPath */
        $customPath = config('modular-lite.stubs_path');

        if ($customPath === null && is_dir(base_path('stubs/modular-lite'))) {
            $customPath = base_path('stubs/modular-lite');
        }

        if ($customPath !== null && file_exists("{$customPath}/{$stubName}.stub")) {
            $content = file_get_contents("{$customPath}/{$stubName}.stub");
            if ($content !== false) {
                return $content;
            }
        }

        $defaultPath = __DIR__."/../stubs/{$stubName}.stub";

        if (! file_exists($defaultPath)) {
            throw new FileNotFoundException("Stub file [{$stubName}.stub] not found at [{$defaultPath}].");
        }

        $content = file_get_contents($defaultPath);

        if ($content === false) {
            throw new FileNotFoundException("Unable to read stub [{$defaultPath}].");
        }

        return $content;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function replacePlaceholders(string $stub, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $stub = str_replace(["{{{$key}}}", "{{ {$key} }}"], $value, $stub);
        }

        return $stub;
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0o755, true);
        }
    }

    protected function writeFile(string $path, string $content, bool $force = false): bool
    {
        if (file_exists($path) && ! $force) {
            $this->components->warn("File [{$path}] already exists. Use --force to overwrite.");

            return false;
        }

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return true;
    }
}
