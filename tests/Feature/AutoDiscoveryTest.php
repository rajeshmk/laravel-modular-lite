<?php

declare(strict_types=1);

use Hatchyu\ModularLite\Discovery\ModuleRegistry;

it('discovers modules dynamically from the filesystem', function () {
    $this->artisan('module:make', ['name' => 'Inventory'])->assertSuccessful();
    $this->artisan('module:make', ['name' => 'Orders'])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $modules = $registry->all();

    expect($modules)->toHaveCount(2)
        ->and($registry->has('Inventory'))->toBeTrue()
        ->and($registry->has('Orders'))->toBeTrue();
});

it('lists all modules via module:list', function () {
    $this->artisan('module:make', ['name' => 'Warehouse'])->assertSuccessful();

    $this->artisan('module:list')
        ->assertSuccessful()
        ->expectsTable(
            ['Module', 'Slug', 'Routes', 'Migrations', 'Models', 'Controllers', 'Provider'],
            [
                ['Warehouse', 'warehouse', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes'],
            ]
        );
});
