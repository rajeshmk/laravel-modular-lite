<?php

declare(strict_types=1);

use Hatchyu\ModularLite\Discovery\ModuleRegistry;

it('generates a complete lightweight CRUD slice via module:make-crud', function () {
    $this->artisan('module:make', ['name' => 'Store'])->assertSuccessful();

    $this->artisan('module:make-crud', [
        'module' => 'Store',
        'name' => 'Product',
    ])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $module = $registry->find('Store');

    expect($module)->not->toBeNull();

    // Model & Factory
    $modelFile = $module->getPath('Models/Product.php');
    $factoryFile = $module->getPath('Database/Factories/ProductFactory.php');
    expect(file_exists($modelFile))->toBeTrue()
        ->and(file_get_contents($modelFile))->toContain('class Product extends Model')
        ->and(file_exists($factoryFile))->toBeTrue();

    // Requests, Resource & Controller
    $storeReq = $module->getPath('Requests/StoreProductRequest.php');
    $updateReq = $module->getPath('Requests/UpdateProductRequest.php');
    $resource = $module->getPath('Resources/ProductResource.php');
    $controller = $module->getPath('Controllers/Api/ProductController.php');
    expect(file_exists($storeReq))->toBeTrue()
        ->and(file_exists($updateReq))->toBeTrue()
        ->and(file_exists($resource))->toBeTrue()
        ->and(file_exists($controller))->toBeTrue()
        ->and(file_get_contents($controller))->toContain('class ProductController')
        ->and(file_get_contents($controller))->toContain('Product::query()');

    // Migration
    $migrations = glob($module->getPath('Database/Migrations/*_create_products_table.php'));
    expect(! empty($migrations))->toBeTrue();

    // Test & Route
    $testFile = $module->getPath('Tests/Feature/ProductControllerTest.php');
    expect(file_exists($testFile))->toBeTrue();

    $apiRoutes = file_get_contents($module->getApiRoutesPath());
    expect($apiRoutes)->toContain("Route::apiResource('products'");
});
