<?php

use Potager\Database\Database;
use Potager\Database\Migration;
use Potager\Database\Migrator;
use Potager\Sillon\Schema\Blueprint;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/potager_migrator_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->database = new Database([
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);

    $this->migrator = new Migrator($this->database);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }
});

it('creates the migrations repository table if it does not exist', function () {
    expect($this->migrator->repositoryExists())->toBeFalse();

    $this->migrator->createRepositoryIfNotExists();

    expect($this->migrator->repositoryExists())->toBeTrue();
    expect($this->migrator->getRan())->toBe([]);
    expect($this->migrator->getLastBatchNumber())->toBe(0);
    expect($this->migrator->getNextBatchNumber())->toBe(1);
});

it('discovers and runs pending migrations', function () {
    // Migration 1: create users table
    $file1 = $this->tempDir . '/2026_10_26_000001_create_users_table.php';
    file_put_contents($file1, <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(): void {
        $this->schema()->dropIfExists('users');
    }
};
PHP);

    // Migration 2: create posts table
    $file2 = $this->tempDir . '/2026_10_26_000002_create_posts_table.php';
    file_put_contents($file2, <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
    }

    public function down(): void {
        $this->schema()->dropIfExists('posts');
    }
};
PHP);

    $pending = $this->migrator->getPendingMigrations($this->tempDir);
    expect($pending)->toHaveCount(2);

    $events = [];
    $this->migrator->setOutputCallback(function ($event, $name) use (&$events) {
        $events[] = [$event, $name];
    });

    $ran = $this->migrator->run($this->tempDir);

    expect($ran)->toBe([
        '2026_10_26_000001_create_users_table',
        '2026_10_26_000002_create_posts_table',
    ]);

    expect($this->database->getSchemaBuilder()->hasTable('users'))->toBeTrue();
    expect($this->database->getSchemaBuilder()->hasTable('posts'))->toBeTrue();

    expect($this->migrator->getRan())->toBe([
        '2026_10_26_000001_create_users_table',
        '2026_10_26_000002_create_posts_table',
    ]);
    expect($this->migrator->getLastBatchNumber())->toBe(1);

    // Verify notifications were triggered
    expect($events)->toContain(['running', '2026_10_26_000001_create_users_table']);
    expect($events)->toContain(['migrated', '2026_10_26_000001_create_users_table']);

    // Re-running without new migrations returns empty
    $secondRun = $this->migrator->run($this->tempDir);
    expect($secondRun)->toBe([]);
});

it('increments batch number for subsequent migration runs', function () {
    // Migration 1
    file_put_contents($this->tempDir . '/2026_10_26_000001_create_t1_table.php', <<<'PHP'
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

    $this->migrator->run($this->tempDir);
    expect($this->migrator->getLastBatchNumber())->toBe(1);

    // Migration 2 added in second batch
    file_put_contents($this->tempDir . '/2026_10_26_000002_create_t2_table.php', <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('t2', fn(Blueprint $table) => $table->id());
    }
    public function down(): void {
        $this->schema()->dropIfExists('t2');
    }
};
PHP);

    $this->migrator->run($this->tempDir);
    expect($this->migrator->getLastBatchNumber())->toBe(2);

    $rows = $this->database->table('potager_migrations')->get();
    expect($rows[0]->batch)->toBe(1);
    expect($rows[1]->batch)->toBe(2);
});

it('rolls back the last batch of migrations', function () {
    // Batch 1
    file_put_contents($this->tempDir . '/2026_10_26_000001_create_t1_table.php', <<<'PHP'
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
    $this->migrator->run($this->tempDir);

    // Batch 2 (two migrations in this batch)
    file_put_contents($this->tempDir . '/2026_10_26_000002_create_t2_table.php', <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('t2', fn(Blueprint $table) => $table->id());
    }
    public function down(): void {
        $this->schema()->dropIfExists('t2');
    }
};
PHP);
    file_put_contents($this->tempDir . '/2026_10_26_000003_create_t3_table.php', <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('t3', fn(Blueprint $table) => $table->id());
    }
    public function down(): void {
        $this->schema()->dropIfExists('t3');
    }
};
PHP);
    $this->migrator->run($this->tempDir);

    expect($this->database->getSchemaBuilder()->hasTable('t1'))->toBeTrue();
    expect($this->database->getSchemaBuilder()->hasTable('t2'))->toBeTrue();
    expect($this->database->getSchemaBuilder()->hasTable('t3'))->toBeTrue();

    // Rollback last batch (batch 2 -> t2 and t3)
    $rolledBack = $this->migrator->rollback($this->tempDir);

    expect($rolledBack)->toBe([
        '2026_10_26_000003_create_t3_table',
        '2026_10_26_000002_create_t2_table',
    ]);

    expect($this->database->getSchemaBuilder()->hasTable('t1'))->toBeTrue();
    expect($this->database->getSchemaBuilder()->hasTable('t2'))->toBeFalse();
    expect($this->database->getSchemaBuilder()->hasTable('t3'))->toBeFalse();
    expect($this->migrator->getLastBatchNumber())->toBe(1);
});

it('supports rollback with step option', function () {
    file_put_contents($this->tempDir . '/2026_10_26_000001_create_t1_table.php', <<<'PHP'
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
    file_put_contents($this->tempDir . '/2026_10_26_000002_create_t2_table.php', <<<'PHP'
<?php
use Potager\Database\Migration;
use Potager\Sillon\Schema\Blueprint;

return new class extends Migration {
    public function up(): void {
        $this->schema()->create('t2', fn(Blueprint $table) => $table->id());
    }
    public function down(): void {
        $this->schema()->dropIfExists('t2');
    }
};
PHP);
    $this->migrator->run($this->tempDir);

    // Rollback only 1 step
    $rolledBack = $this->migrator->rollback($this->tempDir, ['step' => 1]);

    expect($rolledBack)->toBe(['2026_10_26_000002_create_t2_table']);
    expect($this->database->getSchemaBuilder()->hasTable('t1'))->toBeTrue();
    expect($this->database->getSchemaBuilder()->hasTable('t2'))->toBeFalse();
});

it('wipes all tables from the database', function () {
    $this->database->getSchemaBuilder()->create('sample1', fn(Blueprint $table) => $table->id());
    $this->database->getSchemaBuilder()->create('sample2', fn(Blueprint $table) => $table->id());

    expect($this->database->getSchemaBuilder()->hasTable('sample1'))->toBeTrue();
    expect($this->database->getSchemaBuilder()->hasTable('sample2'))->toBeTrue();

    $this->migrator->wipe();

    expect($this->database->getSchemaBuilder()->hasTable('sample1'))->toBeFalse();
    expect($this->database->getSchemaBuilder()->hasTable('sample2'))->toBeFalse();
});

it('refreshes the database cleanly with fresh', function () {
    file_put_contents($this->tempDir . '/2026_10_26_000001_create_users_table.php', <<<'PHP'
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

    $this->migrator->run($this->tempDir);
    expect($this->database->getSchemaBuilder()->hasTable('users'))->toBeTrue();

    // Fresh drops everything and re-runs
    $ran = $this->migrator->fresh($this->tempDir);

    expect($ran)->toBe(['2026_10_26_000001_create_users_table']);
    expect($this->database->getSchemaBuilder()->hasTable('users'))->toBeTrue();
    expect($this->migrator->getLastBatchNumber())->toBe(1);
});
