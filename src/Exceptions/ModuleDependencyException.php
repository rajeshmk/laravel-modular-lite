<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Exceptions;

use RuntimeException;

final class ModuleDependencyException extends RuntimeException
{
    public static function missingDependency(string $module, string $dependency): self
    {
        return new self(
            "Module [{$module}] requires module [{$dependency}], but it was not found in the application."
        );
    }

    public static function disabledDependency(string $module, string $dependency): self
    {
        return new self(
            "Module [{$module}] depends on module [{$dependency}], but [{$dependency}] is currently disabled. Enable it using: php artisan module:enable {$dependency}"
        );
    }

    public static function circularDependency(string $cycle): self
    {
        return new self(
            "Circular module dependency detected: [{$cycle}]. Please break the circular reference."
        );
    }

    public static function dependentModuleActive(string $module, string $dependentModule): self
    {
        return new self(
            "Cannot disable module [{$module}] because active module [{$dependentModule}] depends on it. Disable [{$dependentModule}] first or use --force."
        );
    }
}
