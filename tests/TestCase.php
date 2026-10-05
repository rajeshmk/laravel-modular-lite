<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Tests;

use Hatchyu\ModularLite\ModularLiteServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tmpDir = __DIR__.'/tmp';
        if (is_dir($tmpDir)) {
            $this->removeDirectory($tmpDir);
        }

        mkdir($tmpDir.'/modules', 0o755, true);
        mkdir($tmpDir.'/cache', 0o755, true);
    }

    protected function tearDown(): void
    {
        $tmpDir = __DIR__.'/tmp';
        if (is_dir($tmpDir)) {
            $this->removeDirectory($tmpDir);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ModularLiteServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('modular-lite.path', __DIR__.'/tmp/modules');
        $app['config']->set('modular-lite.cache_path', __DIR__.'/tmp/cache/modules-lite.php');
        $app['config']->set('modular-lite.namespace', 'Modules\\');
    }

    protected function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $filePath = $path.'/'.$file;
            is_dir($filePath) ? $this->removeDirectory($filePath) : unlink($filePath);
        }

        rmdir($path);
    }
}
