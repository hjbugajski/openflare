<?php

declare(strict_types=1);

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Notifier;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
    $this->withoutVite();
});

/**
 * 15 checks that deliberately repeat sort values across the page boundary and
 * leave status_code / response_time_ms / error_message NULL on half of them —
 * the shape that used to 500 or skip rows under keyset pagination.
 *
 * The nullable columns step through several distinct values in groups so the
 * ordering assertions can actually fail: a run of identical values would stay
 * "sorted" under any ordering, including the regression being guarded. Groups
 * keep the repeated-value coverage the id tiebreaker exists for. error_message
 * is zero-padded so binary and locale collations agree on its order.
 */
function seedChecks(Monitor $monitor): void
{
    foreach (range(0, 14) as $index) {
        $isUp = $index % 2 === 0;
        $group = intdiv($index, 4);

        MonitorCheck::factory()->create([
            'monitor_id' => $monitor->id,
            'status' => $isUp ? 'up' : 'down',
            'status_code' => $isUp ? 200 + $group : null,
            'response_time_ms' => $isUp ? 100 + $group : null,
            'error_message' => $isUp ? null : 'timeout '.str_pad((string) $group, 2, '0', STR_PAD_LEFT),
            // Repeated timestamps force the id tiebreaker to carry the order.
            'checked_at' => now()->subMinutes(intdiv($index, 3)),
        ]);
    }
}

function seedIncidents(Monitor $monitor): void
{
    foreach (range(0, 14) as $index) {
        Incident::factory()->create([
            'monitor_id' => $monitor->id,
            'cause' => $index % 2 === 0 ? 'shared cause' : 'other cause',
            'started_at' => now()->subHours(intdiv($index, 2) + 1),
            // A partial unique index allows only one open incident per monitor,
            // which is exactly the NULL ended_at row keyset sorting choked on.
            'ended_at' => $index === 0 ? null : now()->subMinutes($index + 1),
        ]);
    }
}

function seedNotifiers(Monitor $monitor, User $user): void
{
    foreach (range(0, 14) as $index) {
        $notifier = Notifier::factory()->create([
            'user_id' => $user->uuid,
            'name' => 'notifier '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'is_active' => $index % 2 === 0,
        ]);

        $monitor->notifiers()->attach($notifier, ['is_excluded' => false]);
    }
}

/**
 * Fetch every page of one table and return the flattened rows.
 *
 * @param  array<string, mixed>  $query
 * @return array<int, array<string, mixed>>
 */
function allPages(object $test, Monitor $monitor, string $table, array $query = []): array
{
    $rows = [];
    $page = 1;

    do {
        $response = $test->actingAs($test->user)
            ->get(route('monitors.show', [$monitor, ...$query, $table.'_page' => $page]))
            ->assertOk();

        $props = $response->viewData('page')['props'][$table];
        $rows = [...$rows, ...$props['data']];
        $lastPage = $props['last_page'];
        $page++;
    } while ($page <= $lastPage);

    return $rows;
}

it('paginates each table independently', function () {
    seedChecks($this->monitor);
    seedIncidents($this->monitor);
    seedNotifiers($this->monitor, $this->user);

    $this->actingAs($this->user)
        ->get(route('monitors.show', [$this->monitor, 'checks_page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('checks.current_page', 2)
            ->where('checks.total', 15)
            ->has('checks.data', 5)
            ->where('incidents.current_page', 1)
            ->has('incidents.data', 10)
            ->where('notifiers.current_page', 1)
            ->has('notifiers.data', 10)
        );
});

it('keeps totals independent of the requested page', function () {
    seedChecks($this->monitor);
    seedIncidents($this->monitor);
    seedNotifiers($this->monitor, $this->user);

    $this->actingAs($this->user)
        ->get(route('monitors.show', [
            $this->monitor,
            'checks_page' => 2,
            'incidents_page' => 2,
            'notifiers_page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('checks.total', 15)
            ->where('incidents.total', 15)
            ->where('notifiers.total', 15)
        );
});

it('returns an empty page for an out-of-range page number', function () {
    seedChecks($this->monitor);

    // Laravel does not clamp the requested page, so the server echoes it back
    // with no rows. Pinned because the frontend has to handle that shape.
    $this->actingAs($this->user)
        ->get(route('monitors.show', [$this->monitor, 'checks_page' => 999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('checks.current_page', 999)
            ->where('checks.total', 15)
            ->where('checks.last_page', 2)
            ->has('checks.data', 0)
        );
});

it('walks every checks sort across both pages without skipping or repeating rows', function (string $sort, string $direction) {
    seedChecks($this->monitor);

    $rows = allPages($this, $this->monitor, 'checks', [
        'checks_sort' => $sort,
        'checks_direction' => $direction,
    ]);

    $ids = array_column($rows, 'id');

    expect($ids)->toHaveCount(15)
        ->and(array_unique($ids))->toHaveCount(15)
        ->and(array_diff($this->monitor->checks()->pluck('id')->all(), $ids))->toBeEmpty();
})->with([
    ['status', 'asc'], ['status', 'desc'],
    ['status_code', 'asc'], ['status_code', 'desc'],
    ['response_time_ms', 'asc'], ['response_time_ms', 'desc'],
    ['error_message', 'asc'], ['error_message', 'desc'],
    ['checked_at', 'asc'], ['checked_at', 'desc'],
]);

it('walks every incidents sort across both pages without skipping or repeating rows', function (string $sort, string $direction) {
    seedIncidents($this->monitor);

    $rows = allPages($this, $this->monitor, 'incidents', [
        'incidents_sort' => $sort,
        'incidents_direction' => $direction,
    ]);

    $ids = array_column($rows, 'id');

    expect($ids)->toHaveCount(15)
        ->and(array_unique($ids))->toHaveCount(15)
        ->and(array_diff($this->monitor->incidents()->pluck('id')->all(), $ids))->toBeEmpty();
})->with([
    ['status', 'asc'], ['status', 'desc'],
    ['cause', 'asc'], ['cause', 'desc'],
    ['duration', 'asc'], ['duration', 'desc'],
    ['started_at', 'asc'], ['started_at', 'desc'],
    ['ended_at', 'asc'], ['ended_at', 'desc'],
]);

it('walks every notifiers sort across both pages without skipping or repeating rows', function (string $sort, string $direction) {
    seedNotifiers($this->monitor, $this->user);

    $rows = allPages($this, $this->monitor, 'notifiers', [
        'notifiers_sort' => $sort,
        'notifiers_direction' => $direction,
    ]);

    $ids = array_column($rows, 'id');

    expect($ids)->toHaveCount(15)
        ->and(array_unique($ids))->toHaveCount(15);
})->with([
    ['name', 'asc'], ['name', 'desc'],
    ['status', 'asc'], ['status', 'desc'],
    ['type', 'asc'], ['type', 'desc'],
    ['apply_to_all', 'asc'], ['apply_to_all', 'desc'],
]);

it('orders nullable check columns consistently across the page boundary', function (string $sort, string $direction) {
    seedChecks($this->monitor);

    $rows = allPages($this, $this->monitor, 'checks', [
        'checks_sort' => $sort,
        'checks_direction' => $direction,
    ]);

    // NULL placement is driver-specific, but the non-null run must stay sorted
    // and contiguous — a keyset skip would interleave it.
    $values = array_values(array_filter(array_column($rows, $sort), fn ($value) => $value !== null));
    $sorted = $values;
    $direction === 'asc' ? sort($sorted) : rsort($sorted);

    // More than one distinct value, or the comparison below holds under any
    // ordering and the test guards nothing.
    expect(count(array_unique($values)))->toBeGreaterThan(1)
        ->and($values)->toBe($sorted);
})->with([
    ['status_code', 'asc'], ['status_code', 'desc'],
    ['response_time_ms', 'asc'], ['response_time_ms', 'desc'],
    ['error_message', 'asc'], ['error_message', 'desc'],
]);

it('orders checks by checked_at on page two', function (string $direction) {
    seedChecks($this->monitor);

    $rows = allPages($this, $this->monitor, 'checks', ['checks_direction' => $direction]);
    $values = array_column($rows, 'checked_at');
    $sorted = $values;
    $direction === 'asc' ? sort($sorted) : rsort($sorted);

    expect($values)->toBe($sorted);
})->with(['asc', 'desc']);

it('excludes excluded notifiers from the data and the total', function () {
    seedNotifiers($this->monitor, $this->user);

    foreach (range(0, 2) as $index) {
        $excluded = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'name' => 'excluded '.$index,
        ]);

        $this->monitor->notifiers()->attach($excluded, ['is_excluded' => true]);
    }

    $rows = allPages($this, $this->monitor, 'notifiers');

    expect(array_column($rows, 'name'))->not->toContain('excluded 0');

    $this->actingAs($this->user)
        ->get(route('monitors.show', $this->monitor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('notifiers.total', 15));
});
