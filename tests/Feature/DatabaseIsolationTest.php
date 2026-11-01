<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('each test starts with an empty database', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    expect(User::count())->toBe(0);
    User::factory()->create();
    expect(User::count())->toBe(1);
})->repeat(2);
