<?php

declare(strict_types=1);

use Hatchyu\ModularLite\Discovery\ModuleRegistry;
use Hatchyu\ModularLite\Exceptions\ModuleDependencyException;

it('creates modules with default manifest enabled and empty dependencies', function () {
    $this->artisan('module:make', ['name' => 'Users'])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $registry->flush();

    $userModule = $registry->find('Users');
    expect($userModule)->not->toBeNull()
        ->and($userModule->isEnabled())->toBeTrue()
        ->and($userModule->isDisabled())->toBeFalse()
        ->and($userModule->getDependencies())->toBe([]);

    $manifestPath = $userModule->getManifestPath();
    expect(file_exists($manifestPath))->toBeTrue();
});

it('enables and disables modules via artisan commands', function () {
    $this->artisan('module:make', ['name' => 'Notifications'])->assertSuccessful();

    $this->artisan('module:disable', ['module' => 'Notifications'])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $registry->flush();

    $module = $registry->find('Notifications');
    expect($module->isDisabled())->toBeTrue()
        ->and($module->isEnabled())->toBeFalse()
        ->and($registry->disabled()->has('Notifications'))->toBeTrue()
        ->and($registry->enabled()->has('Notifications'))->toBeFalse();

    $this->artisan('module:enable', ['module' => 'Notifications'])->assertSuccessful();
    $registry->flush();

    $module = $registry->find('Notifications');
    expect($module->isEnabled())->toBeTrue()
        ->and($registry->enabled()->has('Notifications'))->toBeTrue();
});

it('resolves modules in topological dependency order', function () {
    $this->artisan('module:make', ['name' => 'Orders'])->assertSuccessful();
    $this->artisan('module:make', ['name' => 'Billing'])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $registry->flush();

    // Set Orders to depend on Billing
    $ordersModule = $registry->find('Orders');
    $ordersModule->writeManifest(['dependencies' => ['Billing']]);

    $registry->flush();
    $enabledNames = $registry->enabled()->keys()->all();

    $billingIndex = array_search('Billing', $enabledNames, true);
    $ordersIndex = array_search('Orders', $enabledNames, true);

    expect($billingIndex)->toBeLessThan($ordersIndex);
});

it('throws ModuleDependencyException when dependent module dependency is disabled', function () {
    $this->artisan('module:make', ['name' => 'Orders'])->assertSuccessful();
    $this->artisan('module:make', ['name' => 'Billing'])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $registry->flush();

    // Orders depends on Billing
    $registry->find('Orders')->writeManifest(['dependencies' => ['Billing']]);

    // Disable Billing
    $this->artisan('module:disable', ['module' => 'Billing', '--force' => true])->assertSuccessful();

    $registry->flush();

    expect(fn () => $registry->enabled())
        ->toThrow(ModuleDependencyException::class, 'Module [Orders] depends on module [Billing], but [Billing] is currently disabled.');
});

it('prevents disabling a module if active modules depend on it unless forced', function () {
    $this->artisan('module:make', ['name' => 'Billing'])->assertSuccessful();
    $this->artisan('module:make', ['name' => 'Orders'])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $registry->flush();

    $registry->find('Orders')->writeManifest(['dependencies' => ['Billing']]);
    $registry->flush();

    // Trying to disable Billing without force should fail
    $this->artisan('module:disable', ['module' => 'Billing'])
        ->assertFailed();

    // Disabling with force should succeed
    $this->artisan('module:disable', ['module' => 'Billing', '--force' => true])
        ->assertSuccessful();
});

it('updates dependency declarations in other modules when renaming a module', function () {
    $this->artisan('module:make', ['name' => 'Auth'])->assertSuccessful();
    $this->artisan('module:make', ['name' => 'Profile'])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $registry->flush();

    $registry->find('Profile')->writeManifest(['dependencies' => ['Auth']]);

    // Rename Auth to Authentication
    $this->artisan('module:rename', [
        'module' => 'Auth',
        'new_name' => 'Authentication',
    ])->assertSuccessful();

    $registry->flush();

    $profileManifest = json_decode((string) file_get_contents($registry->find('Profile')->getManifestPath()), true);
    expect($profileManifest['dependencies'])->toEqual(['Authentication']);
});

it('diagnoses disabled and missing dependencies in module:doctor', function () {
    $this->artisan('module:make', ['name' => 'Inventory'])->assertSuccessful();
    $this->artisan('module:make', ['name' => 'Catalog'])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $registry->flush();

    $registry->find('Inventory')->writeManifest(['dependencies' => ['Catalog', 'NonExistentModule']]);
    $this->artisan('module:disable', ['module' => 'Catalog', '--force' => true])->assertSuccessful();

    $this->artisan('module:doctor', ['module' => 'Inventory', '--strict' => true])
        ->assertFailed();
});
