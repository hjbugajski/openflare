<?php

declare(strict_types=1);

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
