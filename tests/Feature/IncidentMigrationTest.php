<?php

declare(strict_types=1);

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('the incidents table rejects a second open incident for the same monitor', function () {
    $monitor = Monitor::withoutEvents(fn () => Monitor::factory()->create());

    Incident::factory()->ongoing()->create(['monitor_id' => $monitor->id]);

    expect(fn () => DB::table('incidents')->insert([
        'id' => (string) Str::uuid7(),
        'monitor_id' => $monitor->id,
        'started_at' => now(),
        'ended_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});
