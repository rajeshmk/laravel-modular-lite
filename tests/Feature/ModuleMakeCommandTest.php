<?php

declare(strict_types=1);

use Hatchyu\ModularLite\Discovery\ModuleRegistry;

it('scaffolds a complete lightweight module conforming to clean Laravel structure', function () {
    $this->artisan('module:make', ['name' => 'Shop'])
        ->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $module = $registry->find('Shop');

    expect($module)->not->toBeNull()
        ->and($module->getName())->toBe('Shop')
        ->and($module->getSlug())->toBe('shop')
        ->and(file_exists($module->getProviderPath()))->toBeTrue()
        ->and(file_exists($module->getWebRoutesPath()))->toBeTrue()
        ->and(file_exists($module->getApiRoutesPath()))->toBeTrue()
        ->and(file_exists($module->getPath('Database/Seeders/ShopDatabaseSeeder.php')))->toBeTrue()
        ->and(is_dir($module->getPath('Controllers')))->toBeTrue()
        ->and(is_dir($module->getPath('Models')))->toBeTrue()
        ->and(is_dir($module->getPath('Requests')))->toBeTrue()
        ->and(is_dir($module->getPath('Resources')))->toBeTrue()
        ->and(is_dir($module->getPath('Services')))->toBeTrue()
        ->and(is_dir($module->getPath('Database/Migrations')))->toBeTrue()
        ->and(is_dir($module->getPath('Database/Factories')))->toBeTrue()
        ->and(is_dir($module->getPath('Database/Seeders')))->toBeTrue()
        ->and(is_dir($module->getPath('Tests/Feature')))->toBeTrue();
});

it('rejects invalid module names in module:make', function () {
    $this->artisan('module:make', ['name' => '123-Invalid!'])
        ->assertFailed();
});

it('supports --force flag to overwrite existing generated files', function () {
    $this->artisan('module:make', ['name' => 'Blog'])->assertSuccessful();

    $this->artisan('module:make', ['name' => 'Blog'])
        ->assertFailed();

    $this->artisan('module:make', ['name' => 'Blog', '--force' => true])
        ->assertSuccessful();
});

it('generates individual components via cli into proper folders', function () {
    $this->artisan('module:make', ['name' => 'Catalog'])->assertSuccessful();

    // Model
    $this->artisan('module:make-model', ['module' => 'Catalog', 'name' => 'Product'])->assertSuccessful();
    expect(file_exists(__DIR__.'/../tmp/modules/Catalog/Models/Product.php'))->toBeTrue();

    // Controller
    $this->artisan('module:make-controller', ['module' => 'Catalog', 'name' => 'ProductController', '--api' => true])->assertSuccessful();
    expect(file_exists(__DIR__.'/../tmp/modules/Catalog/Controllers/Api/ProductController.php'))->toBeTrue();

    // Request
    $this->artisan('module:make-request', ['module' => 'Catalog', 'name' => 'StoreProductRequest'])->assertSuccessful();
    expect(file_exists(__DIR__.'/../tmp/modules/Catalog/Requests/StoreProductRequest.php'))->toBeTrue();

    // Resource
    $this->artisan('module:make-resource', ['module' => 'Catalog', 'name' => 'ProductResource'])->assertSuccessful();
    expect(file_exists(__DIR__.'/../tmp/modules/Catalog/Resources/ProductResource.php'))->toBeTrue();

    // Service
    $this->artisan('module:make-service', ['module' => 'Catalog', 'name' => 'ProductService'])->assertSuccessful();
    expect(file_exists(__DIR__.'/../tmp/modules/Catalog/Services/ProductService.php'))->toBeTrue();

    // Migration
    $this->artisan('module:make-migration', ['module' => 'Catalog', 'name' => 'create_products_table'])->assertSuccessful();
    $migrations = glob(__DIR__.'/../tmp/modules/Catalog/Database/Migrations/*_create_products_table.php');
    expect(! empty($migrations))->toBeTrue();

    // Seeder
    $this->artisan('module:make-seeder', ['module' => 'Catalog', 'name' => 'ProductSeeder'])->assertSuccessful();
    expect(file_exists(__DIR__.'/../tmp/modules/Catalog/Database/Seeders/ProductSeeder.php'))->toBeTrue();

    // Test
    $this->artisan('module:make-test', ['module' => 'Catalog', 'name' => 'ProductTest'])->assertSuccessful();
    expect(file_exists(__DIR__.'/../tmp/modules/Catalog/Tests/Feature/ProductTest.php'))->toBeTrue();
});
