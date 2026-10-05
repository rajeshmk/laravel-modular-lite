<?php

declare(strict_types=1);

it('runs doctor health inspection successfully', function () {
    $this->artisan('module:make', ['name' => 'Analytics'])->assertSuccessful();

    $this->artisan('module:doctor')
        ->assertSuccessful();

    $this->artisan('module:doctor', ['module' => 'Analytics'])
        ->assertSuccessful();
});

it('auto-repairs missing directories with --fix', function () {
    $this->artisan('module:make', ['name' => 'Reports'])->assertSuccessful();

    unlink(__DIR__.'/../tmp/modules/Reports/Models/.gitkeep');
    rmdir(__DIR__.'/../tmp/modules/Reports/Models');

    $this->artisan('module:doctor', ['--fix' => true])
        ->assertSuccessful();

    expect(is_dir(__DIR__.'/../tmp/modules/Reports/Models'))->toBeTrue();
});
