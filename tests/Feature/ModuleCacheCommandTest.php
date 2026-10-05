<?php

declare(strict_types=1);

use Hatchyu\ModularLite\Discovery\ModuleRegistry;

it('caches and clears module discovery manifest', function () {
    $this->artisan('module:make', ['name' => 'Invoicing'])->assertSuccessful();

    $registry = app(ModuleRegistry::class);
    $cachePath = $registry->getCachePath();

    expect(file_exists($cachePath))->toBeFalse();

    $this->artisan('module:cache')->assertSuccessful();
    expect(file_exists($cachePath))->toBeTrue();

    $this->artisan('module:clear')->assertSuccessful();
    expect(file_exists($cachePath))->toBeFalse();
});
