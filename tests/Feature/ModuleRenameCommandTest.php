<?php

declare(strict_types=1);

use Hatchyu\ModularLite\Discovery\ModuleRegistry;

it('safely renames a module and refactors namespaces, files, and references', function () {
    $this->artisan('module:make', ['name' => 'Warehouse'])->assertSuccessful();
    $this->artisan('module:make-service', ['module' => 'Warehouse', 'name' => 'StockService'])->assertSuccessful();

    $servicePath = __DIR__.'/../tmp/modules/Warehouse/Services/StockService.php';
    expect(file_exists($servicePath))->toBeTrue();

    // Perform rename
    $this->artisan('module:rename', [
        'module' => 'Warehouse',
        'new_name' => 'Logistics',
    ])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $registry->flush();

    expect($registry->find('Warehouse'))->toBeNull()
        ->and($registry->find('Logistics'))->not->toBeNull()
        ->and(is_dir(__DIR__.'/../tmp/modules/Warehouse'))->toBeFalse()
        ->and(is_dir(__DIR__.'/../tmp/modules/Logistics'))->toBeTrue()
        ->and(file_exists(__DIR__.'/../tmp/modules/Logistics/Providers/LogisticsServiceProvider.php'))->toBeTrue();

    // Verify namespace updated
    $refactoredService = __DIR__.'/../tmp/modules/Logistics/Services/StockService.php';
    expect(file_exists($refactoredService))->toBeTrue()
        ->and(file_get_contents($refactoredService))->toContain('namespace Modules\Logistics\Services;');
});
