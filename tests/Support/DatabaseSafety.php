<?php

namespace Tests\Support;

use RuntimeException;

final class DatabaseSafety
{
    public static function assertSafe(string $environment, string $connection, mixed $driver, mixed $database, mixed $url): void
    {
        if ($environment !== 'testing' || $connection !== 'sqlite' || $driver !== 'sqlite' || $database !== ':memory:' || ! in_array($url, [null, ''], true)) {
            throw new RuntimeException('Tests require testing environment and SQLite :memory: without a database URL.');
        }
    }
}
