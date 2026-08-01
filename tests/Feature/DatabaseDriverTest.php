<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The PostgreSQL lanes (CI's test-postgres job, scripts/test-pgsql.sh) only
 * differ from the default lane by their DB_* environment. If that environment
 * is dropped the suite silently falls back to SQLite and stays green, proving
 * nothing. This fails loudly instead.
 */
test('the suite runs against the driver the environment asks for', function () {
    $expected = env('DB_CONNECTION') ?: 'sqlite';

    expect(DB::connection()->getDriverName())->toBe($expected);
});

/**
 * Sessions, queue jobs and check writes all hit the same SQLite file. Under
 * DEFERRED a transaction that reads before it writes fails the lock upgrade
 * with SQLITE_BUSY immediately instead of honouring busy_timeout, surfacing as
 * intermittent "database is locked" 500s.
 */
test('sqlite transactions default to IMMEDIATE', function () {
    expect(config('database.connections.sqlite.transaction_mode'))->toBe('IMMEDIATE');
});

test('a sqlite transaction holds the write lock from BEGIN', function () {
    $file = tempnam(sys_get_temp_dir(), 'openflare-lock-test-');

    $connection = fn (string $mode) => [
        ...config('database.connections.sqlite'),
        'database' => $file,
        'busy_timeout' => 1,
        'transaction_mode' => $mode,
    ];

    config([
        'database.connections.lock_probe_writer' => $connection(config('database.connections.sqlite.transaction_mode')),
        'database.connections.lock_probe_other' => $connection('DEFERRED'),
    ]);

    try {
        DB::connection('lock_probe_writer')->statement('create table probe (id integer primary key)');
        DB::connection('lock_probe_writer')->beginTransaction();

        // The transaction has issued no statements yet: only IMMEDIATE mode
        // already holds the write lock at this point.
        expect(fn () => DB::connection('lock_probe_other')->insert('insert into probe (id) values (1)'))
            ->toThrow(QueryException::class, 'database is locked');

        DB::connection('lock_probe_writer')->rollBack();
    } finally {
        DB::purge('lock_probe_writer');
        DB::purge('lock_probe_other');

        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($file.$suffix);
        }
    }
});
