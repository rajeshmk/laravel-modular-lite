<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Modules Path
    |--------------------------------------------------------------------------
    |
    | The directory where all modules reside. By default, this is the root
    | "modules/" folder of your Laravel application.
    |
    */
    'path' => base_path('modules'),

    /*
    |--------------------------------------------------------------------------
    | Modules Namespace
    |--------------------------------------------------------------------------
    |
    | The base root PSR-4 namespace corresponding to the modules path.
    | Ensure this mapping is mirrored in your application's composer.json.
    |
    */
    'namespace' => 'Modules\\',

    /*
    |--------------------------------------------------------------------------
    | Cache Path
    |--------------------------------------------------------------------------
    |
    | Path where the compiled module discovery manifest is written.
    |
    */
    'cache_path' => base_path('bootstrap/cache/modules-lite.php'),

    /*
    |--------------------------------------------------------------------------
    | API Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for module API routes.
    |
    */
    'api_prefix' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery Toggles
    |--------------------------------------------------------------------------
    |
    | Enable or disable automated discovery for specific subsystems.
    |
    */
    'autodiscover' => [
        'routes' => true,
        'migrations' => true,
        'views' => true,
        'configs' => true,
        'factories' => true,
        'policies' => true,
        'commands' => true,
        'providers' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Folders
    |--------------------------------------------------------------------------
    |
    | Folder names within the modules directory that should be ignored during
    | module discovery.
    |
    */
    'ignore' => [
        'node_modules',
        'vendor',
        '.git',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Stubs Path
    |--------------------------------------------------------------------------
    |
    | Override package stubs with customized stubs from this path.
    |
    */
    'stubs_path' => null,
];
