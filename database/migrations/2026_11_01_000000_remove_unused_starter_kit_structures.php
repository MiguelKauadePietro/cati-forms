<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['passkeys', 'password_reset_tokens', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'cache'];

    private const COLUMNS = ['email', 'email_verified_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

    public function up(): void
    {
        $testing = app()->environment('testing');
        if (! in_array(config('cache.default'), $testing ? ['file', 'array'] : ['file'], true)
            || config('queue.default') !== 'sync'
            || ! in_array(config('queue.failed.driver'), [null, 'null'], true)
            || ! in_array(config('session.driver'), $testing ? ['database', 'array'] : ['database'], true)) {
            throw new RuntimeException('Cleanup requires file cache, sync queue, disabled failed-job storage and database sessions.');
        }

        // Validate all data and constraints before any DDL: MySQL DDL is not transactional.
        foreach (self::TABLES as $table) {
            if ($table !== 'cache' && Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Cleanup refused: nonempty legacy table '.$table);
            }
        }
        foreach (self::COLUMNS as $column) {
            if (Schema::hasColumn('users', $column) && DB::table('users')->whereNotNull($column)->exists()) {
                throw new RuntimeException('Cleanup refused: populated users column '.$column);
            }
        }
        foreach (Schema::getTableListing() as $table) {
            foreach (Schema::getForeignKeys($table) as $key) {
                if ((! in_array($table, self::TABLES, true) && in_array($key['foreign_table'], self::TABLES, true))
                    || ($key['foreign_table'] === 'users' && array_intersect($key['foreign_columns'], self::COLUMNS))) {
                    throw new RuntimeException('Cleanup refused: dependent foreign key on '.$table);
                }
            }
        }

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
        $columns = array_values(array_filter(self::COLUMNS, fn (string $column): bool => Schema::hasColumn('users', $column)));
        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns) {
                if (in_array('email', $columns, true)) {
                    $table->dropUnique(['email']);
                }
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Cleanup cannot restore discarded data. Restore the private backup or apply a forward migration.');
    }
};
