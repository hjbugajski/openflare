<?php

use App\Models\DailyUptimeRollup;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    Queue::fake();
});

describe('show', function () {
    it('requires authentication', function () {
        $monitor = Monitor::factory()->create();

        $this->getJson(route('api.monitors.show', $monitor))
            ->assertUnauthorized();
    });

    it('returns monitor summary for owner', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.show', $monitor))
            ->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'url',
                'method',
                'interval',
                'is_active',
                'status',
                'latest_check',
                'current_incident',
            ])
            ->assertJson([
                'id' => $monitor->id,
                'name' => $monitor->name,
            ]);
    });

    it('includes latest check when available', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $check = MonitorCheck::factory()->up()->create([
            'monitor_id' => $monitor->id,
            'status_code' => 200,
            'response_time_ms' => 150,
        ]);

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.show', $monitor))
            ->assertOk()
            ->assertJsonPath('latest_check.id', (string) $check->id)
            ->assertJsonPath('latest_check.status', 'up')
            ->assertJsonPath('latest_check.status_code', 200)
            ->assertJsonPath('latest_check.response_time_ms', 150);
    });

    it('includes current incident when active', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $incident = Incident::factory()->create([
            'monitor_id' => $monitor->id,
            'ended_at' => null,
            'cause' => 'timeout',
        ]);

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.show', $monitor))
            ->assertOk()
            ->assertJsonPath('current_incident.id', (string) $incident->id)
            ->assertJsonPath('current_incident.cause', 'timeout');
    });

    it('returns null status when no checks exist', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.monitors.show', $monitor))
            ->assertOk();

        expect($response->json('latest_check'))->toBeNull();
    });

    it('denies access to other users monitors', function () {
        $monitor = Monitor::factory()->create();

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.show', $monitor))
            ->assertForbidden();
    });
});

describe('checks', function () {
    it('requires authentication', function () {
        $monitor = Monitor::factory()->create();

        $this->getJson(route('api.monitors.checks', $monitor))
            ->assertUnauthorized();
    });

    it('returns recent checks for owner', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        MonitorCheck::factory()->up()->count(3)->create(['monitor_id' => $monitor->id]);

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.checks', $monitor))
            ->assertOk()
            ->assertJsonStructure([
                'checks' => [
                    '*' => [
                        'id',
                        'status',
                        'status_code',
                        'response_time_ms',
                        'error_message',
                        'checked_at',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'checks');
    });

    it('returns checks in descending order by checked_at', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $oldest = MonitorCheck::factory()->checkedAt(now()->subHours(2))->create(['monitor_id' => $monitor->id]);
        $newest = MonitorCheck::factory()->checkedAt(now())->create(['monitor_id' => $monitor->id]);
        $middle = MonitorCheck::factory()->checkedAt(now()->subHour())->create(['monitor_id' => $monitor->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.monitors.checks', $monitor))
            ->assertOk();

        $checks = $response->json('checks');
        expect($checks[0]['id'])->toBe((string) $newest->id);
        expect($checks[1]['id'])->toBe((string) $middle->id);
        expect($checks[2]['id'])->toBe((string) $oldest->id);
    });

    it('limits checks to 100', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        MonitorCheck::factory()->count(150)->create(['monitor_id' => $monitor->id]);

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.checks', $monitor))
            ->assertOk()
            ->assertJsonCount(100, 'checks');
    });

    it('denies access to other users monitors', function () {
        $monitor = Monitor::factory()->create();

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.checks', $monitor))
            ->assertForbidden();
    });
});

describe('rollups', function () {
    it('requires authentication', function () {
        $monitor = Monitor::factory()->create();

        $this->getJson(route('api.monitors.rollups', $monitor))
            ->assertUnauthorized();
    });

    it('returns rollup data for owner', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        DailyUptimeRollup::factory()
            ->forDate(now()->subDays(5)->toDateString())
            ->create(['monitor_id' => $monitor->id]);

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.rollups', $monitor))
            ->assertOk()
            ->assertJsonStructure([
                'rollups' => [
                    '*' => [
                        'date',
                        'total_checks',
                        'successful_checks',
                        'uptime_percentage',
                        'avg_response_time_ms',
                    ],
                ],
                'summary' => [
                    'total_checks',
                    'successful_checks',
                    'uptime_percentage',
                    'avg_response_time_ms',
                ],
            ])
            ->assertJsonCount(1, 'rollups');
    });

    it('returns rollups in ascending date order', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        $day1 = DailyUptimeRollup::factory()->forDate(now()->subDays(3)->toDateString())->create(['monitor_id' => $monitor->id]);
        $day2 = DailyUptimeRollup::factory()->forDate(now()->subDays(1)->toDateString())->create(['monitor_id' => $monitor->id]);
        $day3 = DailyUptimeRollup::factory()->forDate(now()->subDays(2)->toDateString())->create(['monitor_id' => $monitor->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.monitors.rollups', $monitor))
            ->assertOk();

        $rollups = $response->json('rollups');
        expect($rollups[0]['date'])->toBe($day1->date->toDateString());
        expect($rollups[1]['date'])->toBe($day3->date->toDateString());
        expect($rollups[2]['date'])->toBe($day2->date->toDateString());
    });

    it('excludes rollups older than 30 days', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        DailyUptimeRollup::factory()->forDate(now()->subDays(31)->toDateString())->create(['monitor_id' => $monitor->id]);
        DailyUptimeRollup::factory()->forDate(now()->subDays(29)->toDateString())->create(['monitor_id' => $monitor->id]);

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.rollups', $monitor))
            ->assertOk()
            ->assertJsonCount(1, 'rollups');
    });

    it('calculates summary statistics correctly', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
        DailyUptimeRollup::factory()->create([
            'monitor_id' => $monitor->id,
            'date' => now()->subDays(2)->toDateString(),
            'total_checks' => 100,
            'successful_checks' => 95,
            'avg_response_time_ms' => 200,
        ]);
        DailyUptimeRollup::factory()->create([
            'monitor_id' => $monitor->id,
            'date' => now()->subDays(1)->toDateString(),
            'total_checks' => 100,
            'successful_checks' => 100,
            'avg_response_time_ms' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.monitors.rollups', $monitor))
            ->assertOk();

        $summary = $response->json('summary');
        expect($summary['total_checks'])->toBe(200);
        expect($summary['successful_checks'])->toBe(195);
        expect($summary['uptime_percentage'])->toBe(97.5);
        expect($summary['avg_response_time_ms'])->toBe(150);
    });

    it('returns null summary stats when no rollups exist', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        $response = $this->actingAs($this->user)
            ->getJson(route('api.monitors.rollups', $monitor))
            ->assertOk();

        $summary = $response->json('summary');
        expect($summary['total_checks'])->toBe(0);
        expect($summary['successful_checks'])->toBe(0);
        expect($summary['uptime_percentage'])->toBeNull();
        expect($summary['avg_response_time_ms'])->toBeNull();
    });

    it('denies access to other users monitors', function () {
        $monitor = Monitor::factory()->create();

        $this->actingAs($this->user)
            ->getJson(route('api.monitors.rollups', $monitor))
            ->assertForbidden();
    });
});
