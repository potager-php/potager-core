<?php

use Potager\Database\Database;
use Potager\Database\Migration;
use Potager\Facades\Database as DatabaseFacade;
use Potager\Facades\Schema;
use Potager\Sillon\Schema\Blueprint;
use Potager\Sillon\Schema\SchemaBuilder;

afterEach(function () {
    Schema::dropIfExists('facade_test_posts');
    Schema::dropIfExists('facade_test_authors');
    Schema::dropIfExists('facade_renamed_posts');
});

it('resolves schema builder via Database service and Database facade', function () {
    expect(DatabaseFacade::schema())->toBeInstanceOf(SchemaBuilder::class);
    expect(DatabaseFacade::getSchemaBuilder())->toBeInstanceOf(SchemaBuilder::class);
});

it('creates and checks tables via Schema facade', function () {
    expect(Schema::hasTable('facade_test_posts'))->toBeFalse();

    Schema::create('facade_test_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content')->nullable();
        $table->boolean('is_published')->default(false);
        $table->timestamps();
    });

    expect(Schema::hasTable('facade_test_posts'))->toBeTrue();
    expect(Schema::hasColumn('facade_test_posts', 'title'))->toBeTrue();
    expect(Schema::hasColumn('facade_test_posts', 'content'))->toBeTrue();
    expect(Schema::hasColumn('facade_test_posts', 'is_published'))->toBeTrue();
    expect(Schema::hasColumn('facade_test_posts', 'created_at'))->toBeTrue();
    expect(Schema::hasColumn('facade_test_posts', 'updated_at'))->toBeTrue();
    expect(Schema::hasColumn('facade_test_posts', 'unknown'))->toBeFalse();

    $columns = Schema::getColumnListing('facade_test_posts');
    expect($columns)->toContain('id', 'title', 'content', 'is_published', 'created_at', 'updated_at');
});

it('alters tables via Schema facade', function () {
    Schema::create('facade_test_posts', function (Blueprint $table) {
        $table->id();
        $table->string('old_title');
        $table->string('to_remove');
    });

    Schema::table('facade_test_posts', function (Blueprint $table) {
        $table->string('new_column')->nullable();
        $table->renameColumn('old_title', 'title');
        $table->dropColumn('to_remove');
    });

    expect(Schema::hasColumn('facade_test_posts', 'new_column'))->toBeTrue();
    expect(Schema::hasColumn('facade_test_posts', 'title'))->toBeTrue();
    expect(Schema::hasColumn('facade_test_posts', 'old_title'))->toBeFalse();
    expect(Schema::hasColumn('facade_test_posts', 'to_remove'))->toBeFalse();
});

it('renames and drops tables via Schema facade', function () {
    Schema::create('facade_test_posts', function (Blueprint $table) {
        $table->id();
    });

    Schema::rename('facade_test_posts', 'facade_renamed_posts');
    expect(Schema::hasTable('facade_renamed_posts'))->toBeTrue();
    expect(Schema::hasTable('facade_test_posts'))->toBeFalse();

    Schema::drop('facade_renamed_posts');
    expect(Schema::hasTable('facade_renamed_posts'))->toBeFalse();

    // dropIfExists on non-existent table should not fail
    Schema::dropIfExists('non_existent_table_123');
    expect(true)->toBeTrue();
});

it('can run migrations using the Schema facade and Migration class', function () {
    $migration = new class extends Migration {
        public function up(): void
        {
            Schema::create('facade_test_authors', function (Blueprint $table) {
                $table->id();
                $table->string('name');
            });

            Schema::create('facade_test_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('author_id')->constrained('facade_test_authors')->cascadeOnDelete();
                $table->string('title');
                $table->timestamps();
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('facade_test_posts');
            Schema::dropIfExists('facade_test_authors');
        }
    };

    // Run up
    $migration->up();
    expect(Schema::hasTable('facade_test_authors'))->toBeTrue();
    expect(Schema::hasTable('facade_test_posts'))->toBeTrue();

    // Verify insert & foreign key cascade
    DatabaseFacade::table('facade_test_authors')->insert(['id' => 1, 'name' => 'John']);
    DatabaseFacade::table('facade_test_posts')->insert([
        'author_id' => 1,
        'title' => 'First Post',
        'created_at' => '2026-09-07 20:00:00',
        'updated_at' => '2026-09-07 20:00:00',
    ]);

    expect(DatabaseFacade::table('facade_test_posts')->count())->toBe(1);

    DatabaseFacade::table('facade_test_authors')->where('id', 1)->delete();
    expect(DatabaseFacade::table('facade_test_posts')->count())->toBe(0);

    // Run down
    $migration->down();
    expect(Schema::hasTable('facade_test_posts'))->toBeFalse();
    expect(Schema::hasTable('facade_test_authors'))->toBeFalse();
});
