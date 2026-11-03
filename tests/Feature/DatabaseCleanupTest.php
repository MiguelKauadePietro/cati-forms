<?php

use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/** Run historical migrations in a separate, disposable SQLite connection. */
function withLegacySchema(Closure $test): void
{
    $original = DB::getDefaultConnection();
    config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
    DB::setDefaultConnection('legacy_test');
    try {
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (basename($file) >= '2026_11_01_000000_remove_unused_starter_kit_structures.php') {
                break;
            }
            (require $file)->up();
        }
        $test(require database_path('migrations/2026_11_01_000000_remove_unused_starter_kit_structures.php'));
    } finally {
        DB::disconnect('legacy_test');
        DB::setDefaultConnection($original);
    }
}

test('cleanup preserves users sessions credentials and remember tokens', function () {
    withLegacySchema(function ($migration) {
        $user = ['name' => 'Legacy name', 'username' => 'legacy_user', 'password' => 'preserved-hash', 'remember_token' => 'preserved-token'];
        DB::table('users')->insert($user);
        DB::table('sessions')->insert(['id' => 'legacy-session', 'payload' => 'preserved', 'last_activity' => 1]);
        DB::table('cache')->insert(['key' => 'transient', 'value' => 'cached', 'expiration' => 1]);
        $migration->up();
        expect((array) DB::table('users')->first())->toMatchArray($user);
        expect(DB::table('sessions')->first()->payload)->toBe('preserved');
        expect(Schema::hasTable('cache'))->toBeFalse();
        expect(Schema::hasColumn('users', 'email'))->toBeFalse();
    });
});

test('cleanup refuses populated legacy fields before dropping anything', function (string $column, string $value) {
    withLegacySchema(function ($migration) use ($column, $value) {
        DB::table('users')->insert(['name' => 'Legacy', 'username' => 'legacy_user', 'password' => 'hash', $column => $value]);
        expect(fn () => $migration->up())->toThrow(RuntimeException::class);
        expect(Schema::hasTable('passkeys'))->toBeTrue();
        expect(DB::table('users')->value($column))->toBe($value);
    });
})->with([
    ['email', 'legacy@example.com'], ['email_verified_at', '2026-01-01 00:00:00'],
    ['two_factor_secret', 'secret'], ['two_factor_recovery_codes', 'codes'], ['two_factor_confirmed_at', '2026-01-01 00:00:00'],
]);

test('cleanup refuses queued work before dropping anything', function () {
    withLegacySchema(function ($migration) {
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => 'pending', 'attempts' => 0, 'available_at' => 1, 'created_at' => 1]);
        expect(fn () => $migration->up())->toThrow(RuntimeException::class);
        expect(Schema::hasTable('passkeys'))->toBeTrue();
        expect(DB::table('jobs')->count())->toBe(1);
    });
});

test('cleanup refuses database cache driver', function () {
    withLegacySchema(function ($migration) {
        config(['cache.default' => 'database']);
        expect(fn () => $migration->up())->toThrow(RuntimeException::class);
        expect(Schema::hasTable('passkeys'))->toBeTrue();
    });
});

test('cleanup refuses unexpected foreign key dependencies', function () {
    withLegacySchema(function ($migration) {
        Schema::create('external_dependency', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passkey_id')->constrained('passkeys');
        });
        expect(fn () => $migration->up())->toThrow(RuntimeException::class);
        expect(Schema::hasTable('passkeys'))->toBeTrue();
    });
});

test('historical username migration preserves account data before cleanup', function () {
    withLegacySchema(function () {
        $migration = require database_path('migrations/2026_10_30_000000_add_username_to_users_table.php');
        $migration->down();
        $id = DB::table('users')->insertGetId(['name' => 'Legacy', 'email' => 'legacy@example.com', 'password' => 'hash']);
        $migration->up();
        expect(DB::table('users')->find($id)->username)->toBe('user_'.$id);
        expect(DB::table('users')->find($id)->email)->toBe('legacy@example.com');
    });
});

test('file cache supports rate limiting and locks without database tables', function () {
    $directory = sys_get_temp_dir().'/cati-cache-test-'.bin2hex(random_bytes(8));
    config(['cache.stores.file.path' => $directory.'/data', 'cache.stores.file.lock_path' => $directory.'/locks']);
    try {
        $store = app('cache')->store('file');
        $limiter = new RateLimiter($store);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $limiter->hit('login-probe', 60);
        }
        expect($limiter->tooManyAttempts('login-probe', 5))->toBeTrue();
        $limiter->clear('login-probe');
        expect($limiter->tooManyAttempts('login-probe', 5))->toBeFalse();
        $lock = $store->lock('lock-probe', 10);
        expect($lock->get())->toBeTrue();
        expect($lock->release())->toBeTrue();
        expect(Schema::hasTable('cache'))->toBeFalse();
        expect(Schema::hasTable('cache_locks'))->toBeFalse();
    } finally {
        File::deleteDirectory($directory);
    }
});
