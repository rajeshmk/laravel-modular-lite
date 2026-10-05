<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Discovery;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class FactoryGuesser
{
    public function register(string $moduleNamespace): void
    {
        $rootNamespace = rtrim($moduleNamespace, '\\');

        Factory::guessFactoryNamesUsing(function (string $modelName) use ($rootNamespace): string {
            if (Str::startsWith($modelName, $rootNamespace.'\\')) {
                $afterRoot = Str::after($modelName, $rootNamespace.'\\');
                $moduleName = Str::before($afterRoot, '\\');
                $relativeModel = Str::after($afterRoot, $moduleName.'\\');

                if (Str::startsWith($relativeModel, 'Models\\')) {
                    $relativeModel = Str::after($relativeModel, 'Models\\');
                }

                $modelBase = class_basename($relativeModel);
                $nested = Str::beforeLast($relativeModel, $modelBase);
                $nestedNamespace = $nested !== '' ? trim(str_replace('/', '\\', $nested), '\\').'\\' : '';

                return "{$rootNamespace}\\{$moduleName}\\Database\\Factories\\{$nestedNamespace}{$modelBase}Factory";
            }

            $modelName = Str::startsWith($modelName, 'App\\Models\\')
                ? Str::after($modelName, 'App\\Models\\')
                : Str::after($modelName, 'App\\');

            return 'Database\\Factories\\'.$modelName.'Factory';
        });
    }
}
