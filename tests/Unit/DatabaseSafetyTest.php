<?php

use Tests\Support\DatabaseSafety;

test('test database guard accepts only isolated SQLite', function () {
    DatabaseSafety::assertSafe('testing', 'sqlite', 'sqlite', ':memory:', null);
    expect(true)->toBeTrue();
});

test('test database guard refuses unsafe configuration', function (string $env, string $connection, string $driver, string $database, ?string $url) {
    expect(fn () => DatabaseSafety::assertSafe($env, $connection, $driver, $database, $url))
        ->toThrow(RuntimeException::class);
})->with([
    'production' => ['production', 'sqlite', 'sqlite', ':memory:', null],
    'mysql' => ['testing', 'mysql', 'mysql', ':memory:', null],
    'different driver' => ['testing', 'sqlite', 'mysql', ':memory:', null],
    'persistent SQLite' => ['testing', 'sqlite', 'sqlite', '/tmp/database.sqlite', null],
    'URL override' => ['testing', 'sqlite', 'sqlite', ':memory:', 'mysql://unused'],
]);
