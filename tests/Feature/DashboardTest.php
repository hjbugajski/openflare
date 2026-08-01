<?php

declare(strict_types=1);

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Three incidents with distinct monitor names, causes, durations and start
 * times, so every whitelisted sort has an unambiguous winner.
 */
function seedSortableIncidents(User $user): void
{
    $alpha = Monitor::factory()->create(['user_id' => $user->uuid, 'name' => 'alpha monitor']);
    $mike = Monitor::factory()->create(['user_id' => $user->uuid, 'name' => 'mike monitor']);
    $zulu = Monitor::factory()->create(['user_id' => $user->uuid, 'name' => 'zulu monitor']);

    Incident::factory()->create([
        'monitor_id' => $alpha->id,
        'cause' => 'aaa cause',
        'started_at' => now()->subDays(6),
        'ended_at' => now()->subDays(5),
    ]);

    Incident::factory()->create([
        'monitor_id' => $mike->id,
        'cause' => 'mmm cause',
        'started_at' => now()->subDays(4),
        'ended_at' => now()->subDay(),
    ]);

    // Open incident: ended_at NULL used to blow up keyset pagination.
    Incident::factory()->create([
        'monitor_id' => $zulu->id,
        'cause' => 'zzz cause',
        'started_at' => now()->subDays(2),
        'ended_at' => null,
    ]);
}

function seedIncidentPages(User $user, int $count = 15): void
{
    $monitor = Monitor::factory()->create(['user_id' => $user->uuid]);

    foreach (range(1, $count) as $offset) {
        Incident::factory()->create([
            'monitor_id' => $monitor->id,
            'started_at' => now()->subHours($offset),
            // A partial unique index allows only one open incident per monitor.
            'ended_at' => now()->subHours($offset)->addMinutes(10),
        ]);
    }
}

/**
 * @return array<int, string>
 */
function dashboardIncidents(TestResponse $response): array
{
    return $response->viewData('page')['props']['incidents']['data'];
}

/**
 * @return array<int, string>
 */
function dashboardIncidentIds(TestResponse $response): array
{
    return collect(dashboardIncidents($response))->pluck('id')->all();
}

test('guests are redirected to the login page', function () {
    $this->get(route('home'))->assertRedirect('/auth/login');
});

test('authenticated users can visit the home page', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('home'))->assertOk();
});

describe('incidents table', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->withoutVite();
    });

    it('paginates incidents with the incidents_page param', function () {
        seedIncidentPages($this->user);

        $first = $this->actingAs($this->user)->get(route('home'))->assertOk();
        $second = $this->actingAs($this->user)->get(route('home', ['incidents_page' => 2]))->assertOk();

        $firstIds = dashboardIncidentIds($first);
        $secondIds = dashboardIncidentIds($second);

        expect($firstIds)->toHaveCount(10)
            ->and($secondIds)->toHaveCount(5)
            ->and(array_intersect($firstIds, $secondIds))->toBeEmpty()
            ->and(array_unique([...$firstIds, ...$secondIds]))->toHaveCount(15);

        $second->assertInertia(fn ($page) => $page
            ->where('incidents.current_page', 2)
            ->where('incidents.last_page', 2)
            ->where('incidents.total', 15)
        );
    });

    it('sorts by every whitelisted column in both directions', function (string $sort, string $direction, ?string $expectedCause) {
        seedSortableIncidents($this->user);

        $response = $this->actingAs($this->user)
            ->get(route('home', ['sort' => $sort, 'direction' => $direction]))
            ->assertOk();

        $data = dashboardIncidents($response);

        expect($data)->toHaveCount(3);

        if ($expectedCause !== null) {
            expect($data[0]['cause'])->toBe($expectedCause);
        }
    })->with([
        ['monitor', 'asc', 'aaa cause'],
        ['monitor', 'desc', 'zzz cause'],
        ['cause', 'asc', 'aaa cause'],
        ['cause', 'desc', 'zzz cause'],
        // A=1 day, Z=2 days (still open), M=3 days.
        ['duration', 'asc', 'aaa cause'],
        ['duration', 'desc', 'mmm cause'],
        ['started_at', 'asc', 'aaa cause'],
        ['started_at', 'desc', 'zzz cause'],
        // status and ended_at order NULLs per driver, asserted separately below.
        ['status', 'asc', null],
        ['status', 'desc', null],
        ['ended_at', 'asc', null],
        ['ended_at', 'desc', null],
    ]);

    it('sorts open incidents first ascending and resolved first descending', function () {
        seedSortableIncidents($this->user);

        $ascending = $this->actingAs($this->user)
            ->get(route('home', ['sort' => 'status', 'direction' => 'asc']))
            ->assertOk();

        $descending = $this->actingAs($this->user)
            ->get(route('home', ['sort' => 'status', 'direction' => 'desc']))
            ->assertOk();

        expect(dashboardIncidents($ascending)[0]['ended_at'])->toBeNull()
            ->and(dashboardIncidents($descending)[0]['ended_at'])->not->toBeNull();
    });

    it('orders resolved incidents by ended_at regardless of null placement', function (string $direction) {
        seedSortableIncidents($this->user);

        $response = $this->actingAs($this->user)
            ->get(route('home', ['sort' => 'ended_at', 'direction' => $direction]))
            ->assertOk();

        $endedAt = collect(dashboardIncidents($response))
            ->pluck('ended_at')
            ->filter()
            ->values()
            ->all();

        $expected = $endedAt;
        $direction === 'asc' ? sort($expected) : rsort($expected);

        expect($endedAt)->toBe($expected);
    })->with(['asc', 'desc']);

    it('falls back to the default sort for unknown sort and direction values', function () {
        seedSortableIncidents($this->user);

        $response = $this->actingAs($this->user)
            ->get(route('home', ['sort' => 'nonsense', 'direction' => 'sideways']))
            ->assertOk();

        // Default is started_at desc: the most recently started incident wins.
        expect(dashboardIncidents($response)[0]['cause'])->toBe('zzz cause');
    });

    it('excludes incidents older than seven days', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        Incident::factory()->create([
            'monitor_id' => $monitor->id,
            'cause' => 'recent',
            'started_at' => now()->subDay(),
            'ended_at' => null,
        ]);

        Incident::factory()->create([
            'monitor_id' => $monitor->id,
            'cause' => 'stale',
            'started_at' => now()->subDays(10),
            'ended_at' => now()->subDays(9),
        ]);

        $this->actingAs($this->user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('incidents.total', 1)
                ->where('incidents.data.0.cause', 'recent')
            );
    });

    it('ignores page params the client never sends', function (string $param) {
        seedIncidentPages($this->user);

        $expected = dashboardIncidentIds($this->actingAs($this->user)->get(route('home'))->assertOk());

        $response = $this->actingAs($this->user)
            ->get(route('home', [$param => 2]))
            ->assertOk();

        expect(dashboardIncidentIds($response))->toBe($expected);

        $response->assertInertia(fn ($page) => $page->where('incidents.current_page', 1));
    })->with(['page', 'cursor', 'incidents_cursor']);
});
