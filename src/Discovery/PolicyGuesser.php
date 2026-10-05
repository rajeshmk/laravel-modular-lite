<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Discovery;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class PolicyGuesser
{
    public function register(string $moduleNamespace): void
    {
        $rootNamespace = rtrim($moduleNamespace, '\\');

        Gate::guessPolicyNamesUsing(function (string $modelClass) use ($rootNamespace): array|string {
            if (Str::startsWith($modelClass, $rootNamespace.'\\')) {
                $afterRoot = Str::after($modelClass, $rootNamespace.'\\');
                $moduleName = Str::before($afterRoot, '\\');
                $relativeModel = Str::after($afterRoot, $moduleName.'\\');

                if (Str::startsWith($relativeModel, 'Models\\')) {
                    $relativeModel = Str::after($relativeModel, 'Models\\');
                }

                $modelBase = class_basename($relativeModel);
                $nested = Str::beforeLast($relativeModel, $modelBase);
                $nestedNamespace = $nested !== '' ? trim(str_replace('/', '\\', $nested), '\\').'\\' : '';

                return [
                    "{$rootNamespace}\\{$moduleName}\\Policies\\{$nestedNamespace}{$modelBase}Policy",
                    "{$rootNamespace}\\{$moduleName}\\Policies\\{$modelBase}Policy",
                ];
            }

            $classDirname = str_replace('/', '\\', dirname(str_replace('\\', '/', $modelClass)));

            return [
                $classDirname.'\\Policies\\'.class_basename($modelClass).'Policy',
                'App\\Policies\\'.class_basename($modelClass).'Policy',
            ];
        });
    }
}
