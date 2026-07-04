<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('monitors table has an index on user_id', function () {
    expect(Schema::hasIndex('monitors', 'monitors_user_id_index'))->toBeTrue();
});

test('notifiers table has an index on user_id', function () {
    expect(Schema::hasIndex('notifiers', 'notifiers_user_id_index'))->toBeTrue();
});
