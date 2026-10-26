<?php

use Potager\App;
use Potager\Console\IO\ArgvInput;
use Potager\Console\IO\Output;
use Potager\Console\Kernel;
use Potager\Database\Database;
use Potager\Database\Migrator;
use Potager\Sillon\Schema\Blueprint;

describe('Migration CLI Commands', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/potager_console_migrator_test_' . uniqid();
        mkdir($this->tempDir . '/database/migrations', 0755, true);

        $this->app = App::create($this->tempDir);

        // Bind in-memory sqlite database
        $this->database = new Database([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $this->app->getContainer()->instance(Database::class, $this->database);

        $this->migrator = new Migrator($this->database);
        $this->app->getContainer()->instance(Migrator::class, $this->migrator);

        $this->kernel = new Kernel($this->app->getContainer());
        $this->kernel->registerBuiltins();
    });

    afterEach(function () {
        $cleanup = function ($dir) use (&$cleanup) {
            if (!is_dir($dir)) return;
            $items = array_diff(scandir($dir), ['.', '..']);
            foreach ($items as $item) {
                $path = $dir . '/' . $item;
                is_dir($path) ? $cleanup($path) : unlink($path);
            }
            rmdir($dir);
        };
        $cleanup($this->tempDir);
    });

    it('creates a migration with make:migration and auto-detects create stub', function () {
        $input = new ArgvInput(['farmer', 'make:migration', 'CreateUsersTable']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $result = ob_get_clean();

        expect($code)->toBe(0);
        expect($result)->toContain('created successfully');

        $files = glob($this->tempDir . '/database/migrations/*_create_users_table.php');
        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("Schema::create('users'");
        expect($content)->toContain("Schema::dropIfExists('users'");
    });

    it('creates a migration using make alias and table option', function () {
        $input = new ArgvInput(['farmer', 'make', 'add_votes_to_posts_table', '--table=posts']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $result = ob_get_clean();

        expect($code)->toBe(0);
        expect($result)->toContain('created successfully');

        $files = glob($this->tempDir . '/database/migrations/*_add_votes_to_posts_table.php');
        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("Schema::table('posts'");
    });

    it('creates a migration with explicit --create option', function () {
        $input = new ArgvInput(['farmer', 'make:migration', 'create_custom_table', '--create=orders']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $result = ob_get_clean();

        expect($code)->toBe(0);

        $files = glob($this->tempDir . '/database/migrations/*_create_custom_table.php');
        expect($files)->toHaveCount(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain("Schema::create('orders'");
    });

    it('runs pending migrations with db:migrate', function () {
        // Create 2 migration files
        $file1 = $this->tempDir . '/database/migrations/2026_10_26_000001_create_authors_table.php';
        file_put_contents($file1, <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('authors', fn(Blueprint $table) => $table->id());
    }
    public function down(): void {
        $this->schema()->dropIfExists('authors');
    }
};
PHP);

        $file2 = $this->tempDir . '/database/migrations/2026_10_26_000002_create_books_table.php';
        file_put_contents($file2, <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('books', fn(Blueprint $table) => $table->id());
    }
    public function down(): void {
        $this->schema()->dropIfExists('books');
    }
};
PHP);

        $input = new ArgvInput(['farmer', 'db:migrate']);
        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle($input, $output);
        $result = ob_get_clean();

        expect($code)->toBe(0);
        expect($result)->toContain('RUNNING  2026_10_26_000001_create_authors_table');
        expect($result)->toContain('DONE     2026_10_26_000001_create_authors_table');
        expect($result)->toContain('RUNNING  2026_10_26_000002_create_books_table');
        expect($result)->toContain('DONE     2026_10_26_000002_create_books_table');
        expect($result)->toContain('Database migrated successfully');

        expect($this->database->getSchemaBuilder()->hasTable('authors'))->toBeTrue();
        expect($this->database->getSchemaBuilder()->hasTable('books'))->toBeTrue();

        // Second run: nothing to migrate
        ob_start();
        $code2 = $this->kernel->handle(new ArgvInput(['farmer', 'db:migrate']), $output);
        $result2 = ob_get_clean();

        expect($code2)->toBe(0);
        expect($result2)->toContain('Nothing to migrate');
    });

    it('rolls back migrations with db:rollback', function () {
        $file1 = $this->tempDir . '/database/migrations/2026_10_26_000001_create_t1_table.php';
        file_put_contents($file1, <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('t1', fn(Blueprint $table) => $table->id());
    }
    public function down(): void {
        $this->schema()->dropIfExists('t1');
    }
};
PHP);
        $output = new Output(false);

        ob_start();
        $this->kernel->handle(new ArgvInput(['farmer', 'db:migrate']), $output);
        ob_get_clean();

        expect($this->database->getSchemaBuilder()->hasTable('t1'))->toBeTrue();

        ob_start();
        $code = $this->kernel->handle(new ArgvInput(['farmer', 'db:rollback']), $output);
        $result = ob_get_clean();

        expect($code)->toBe(0);
        expect($result)->toContain('ROLLING BACK  2026_10_26_000001_create_t1_table');
        expect($result)->toContain('ROLLED BACK   2026_10_26_000001_create_t1_table');
        expect($this->database->getSchemaBuilder()->hasTable('t1'))->toBeFalse();

        // Rollback again when empty
        ob_start();
        $code2 = $this->kernel->handle(new ArgvInput(['farmer', 'db:rollback']), $output);
        $result2 = ob_get_clean();

        expect($code2)->toBe(0);
        expect($result2)->toContain('Nothing to rollback');
    });

    it('wipes the database with db:wipe', function () {
        $this->database->getSchemaBuilder()->create('dummy1', fn(Blueprint $table) => $table->id());
        $this->database->getSchemaBuilder()->create('dummy2', fn(Blueprint $table) => $table->id());

        expect($this->database->getSchemaBuilder()->hasTable('dummy1'))->toBeTrue();
        expect($this->database->getSchemaBuilder()->hasTable('dummy2'))->toBeTrue();

        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle(new ArgvInput(['farmer', 'db:wipe']), $output);
        $result = ob_get_clean();

        expect($code)->toBe(0);
        expect($result)->toContain('Dropped all tables successfully');
        expect($this->database->getSchemaBuilder()->hasTable('dummy1'))->toBeFalse();
        expect($this->database->getSchemaBuilder()->hasTable('dummy2'))->toBeFalse();
    });

    it('refreshes the database and re-runs migrations with db:fresh', function () {
        $file = $this->tempDir . '/database/migrations/2026_10_26_000001_create_users_table.php';
        file_put_contents($file, <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('users', fn(Blueprint $table) => $table->id());
    }
    public function down(): void {
        $this->schema()->dropIfExists('users');
    }
};
PHP);

        // Pre-create an extra table
        $this->database->getSchemaBuilder()->create('abandoned_table', fn(Blueprint $table) => $table->id());
        expect($this->database->getSchemaBuilder()->hasTable('abandoned_table'))->toBeTrue();

        $output = new Output(false);

        ob_start();
        $code = $this->kernel->handle(new ArgvInput(['farmer', 'db:fresh']), $output);
        $result = ob_get_clean();

        expect($code)->toBe(0);
        expect($result)->toContain('Dropped all tables successfully');
        expect($result)->toContain('Database refreshed and migrated successfully');

        expect($this->database->getSchemaBuilder()->hasTable('abandoned_table'))->toBeFalse();
        expect($this->database->getSchemaBuilder()->hasTable('users'))->toBeTrue();
    });
});
