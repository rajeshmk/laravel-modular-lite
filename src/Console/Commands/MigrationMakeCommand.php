<?php

declare(strict_types=1);

namespace Hatchyu\ModularLite\Console\Commands;

use Hatchyu\ModularLite\Console\GeneratorCommand;
use Illuminate\Support\Str;

class MigrationMakeCommand extends GeneratorCommand
{
    protected $signature = 'module:make-migration
                            {module : The name of the module}
                            {name : The name of the migration (e.g. create_products_table)}
                            {--force : Overwrite the file if it already exists}';

    protected $description = 'Create a new database migration file inside Database/Migrations of a module';

    public function handle(): int
    {
        $module = $this->getModule();

        /** @var string $rawName */
        $rawName = $this->argument('name');
        $migrationName = Str::snake(trim($rawName));

        $tableName = 'table_name';
        if (preg_match('/^create_(.+)_(?:table|table_migration)$/', $migrationName, $matches)) {
            $tableName = $matches[1];
        } elseif (preg_match('/^create_(.+)$/', $migrationName, $matches)) {
            $tableName = $matches[1];
        }

        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]+/', '_', trim($tableName, '_')) ?: 'items';

        $migrationsDir = $module->getMigrationsPath();
        $this->ensureDirectoryExists($migrationsDir);

        $force = (bool) $this->option('force');

        if (! $force && is_dir($migrationsDir)) {
            $existing = glob($migrationsDir.'/*_'.$migrationName.'.php');
            if (! empty($existing)) {
                $this->components->error("Migration [{$migrationName}] already exists at [{$existing[0]}]. Use --force to proceed.");

                return self::FAILURE;
            }
        }

        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$migrationName}.php";
        $filePath = $migrationsDir.DIRECTORY_SEPARATOR.$fileName;

        $stub = "<?php\n\ndeclare(strict_types=1);\n\nuse Illuminate\Database\Migrations\Migration;\nuse Illuminate\Database\Schema\Blueprint;\nuse Illuminate\Support\Facades\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('{$sanitizedTable}', function (Blueprint \$table): void {\n            \$table->id();\n            \$table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('{$sanitizedTable}');\n    }\n};\n";

        if ($this->writeFile($filePath, $stub, $force)) {
            $this->components->info("Migration [{$fileName}] created successfully at [{$filePath}].");

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
