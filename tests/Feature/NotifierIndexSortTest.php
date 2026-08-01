<?php

declare(strict_types=1);

use App\Models\Monitor;
use App\Models\Notifier;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->withoutVite();
});

/**
 * Attach $attached monitors to a notifier, marking $excluded of them excluded,
 * so both withCount aliases hold distinct, sortable values.
 */
function notifierWithCounts(User $user, string $name, int $attached, int $excluded): Notifier
{
    $notifier = Notifier::factory()->create(['user_id' => $user->uuid, 'name' => $name]);

    foreach (range(1, $attached) as $index) {
        $monitor = Monitor::factory()->create(['user_id' => $user->uuid]);
        $notifier->monitors()->attach($monitor, ['is_excluded' => $index <= $excluded]);
    }

    return $notifier;
}

it('sorts by the monitors_count alias', function (string $direction, string $expected) {
    notifierWithCounts($this->user, 'few', 1, 0);
    notifierWithCounts($this->user, 'some', 3, 1);
    notifierWithCounts($this->user, 'many', 5, 4);

    $this->actingAs($this->user)
        ->get(route('notifiers.index', ['sort' => 'monitors_count', 'direction' => $direction]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('notifiers.data.0.name', $expected));
})->with([
    ['asc', 'few'],
    ['desc', 'many'],
]);

it('sorts by the excluded_monitors_count alias', function (string $direction, string $expected) {
    notifierWithCounts($this->user, 'few', 1, 0);
    notifierWithCounts($this->user, 'some', 3, 1);
    notifierWithCounts($this->user, 'many', 5, 4);

    $this->actingAs($this->user)
        ->get(route('notifiers.index', ['sort' => 'excluded', 'direction' => $direction]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('notifiers.data.0.name', $expected));
})->with([
    ['asc', 'few'],
    ['desc', 'many'],
]);

it('paginates with the notifiers_page param', function () {
    foreach (range(1, 15) as $index) {
        Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'name' => 'notifier '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $first = $this->actingAs($this->user)->get(route('notifiers.index'))->assertOk();
    $second = $this->actingAs($this->user)
        ->get(route('notifiers.index', ['notifiers_page' => 2]))
        ->assertOk();

    $firstIds = array_column($first->viewData('page')['props']['notifiers']['data'], 'id');
    $secondIds = array_column($second->viewData('page')['props']['notifiers']['data'], 'id');

    expect($firstIds)->toHaveCount(10)
        ->and($secondIds)->toHaveCount(5)
        ->and(array_intersect($firstIds, $secondIds))->toBeEmpty()
        ->and(array_unique([...$firstIds, ...$secondIds]))->toHaveCount(15);

    $second->assertInertia(fn ($page) => $page
        ->where('notifiers.current_page', 2)
        ->where('notifiers.last_page', 2)
        ->where('notifiers.total', 15)
    );
});

it('reports the total across all pages', function () {
    Notifier::factory()->count(3)->create(['user_id' => $this->user->uuid]);
    Notifier::factory()->count(2)->create();

    $this->actingAs($this->user)
        ->get(route('notifiers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('notifiers.total', 3));
});
