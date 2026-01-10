<?php

declare(strict_types=1);

use App\Models\DailyUptimeRollup;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use Illuminate\Support\Carbon;

test('preferences can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('settings.show'))
        ->patch(route('settings.preferences.update'), [
            'monitors_view' => 'table',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.show'));

    expect($user->refresh()->getPreference('monitors_view'))->toBe('table');
});

test('preferences can update timezone', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('settings.show'))
        ->patch(route('settings.preferences.update'), [
            'timezone' => 'UTC',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.show'));

    expect($user->refresh()->getPreference('timezone'))->toBe('UTC');
});

test('timezone change recomputes rollups', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10 04:00:00', 'America/Los_Angeles'));

    $user = User::factory()->create(['preferences' => ['timezone' => 'UTC']]);
    $monitor = Monitor::factory()->create(['user_id' => $user->uuid]);

    MonitorCheck::factory()
        ->for($monitor)
        ->checkedAt(Carbon::parse('2026-01-09 12:00:00', 'UTC'))
        ->create();

    DailyUptimeRollup::factory()
        ->forDate('2026-01-10')
        ->create([
            'monitor_id' => $monitor->id,
            'total_checks' => 5,
            'successful_checks' => 5,
            'uptime_percentage' => 100.00,
        ]);

    $response = $this
        ->actingAs($user)
        ->from(route('settings.show'))
        ->patch(route('settings.preferences.update'), [
            'timezone' => 'America/Los_Angeles',
        ]);

    $response->assertSessionHasNoErrors();

    $rollup = DailyUptimeRollup::query()
        ->where('monitor_id', $monitor->id)
        ->whereDate('date', '2026-01-09')
        ->first();

    expect($rollup)->not->toBeNull();
    expect($rollup->total_checks)->toBe(1);
    expect(
        DailyUptimeRollup::query()
            ->where('monitor_id', $monitor->id)
            ->whereDate('date', '2026-01-10')
            ->exists()
    )->toBeFalse();

    Carbon::setTestNow();
});

test('preferences update validates monitors_view value', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('settings.show'))
        ->patch(route('settings.preferences.update'), [
            'monitors_view' => 'invalid-value',
        ]);

    $response
        ->assertSessionHasErrors('monitors_view')
        ->assertRedirect(route('settings.show'));
});

test('preferences update validates timezone value', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('settings.show'))
        ->patch(route('settings.preferences.update'), [
            'timezone' => 'Not/A_Timezone',
        ]);

    $response
        ->assertSessionHasErrors('timezone')
        ->assertRedirect(route('settings.show'));
});

test('preferences update allows cards value', function () {
    $user = User::factory()->create(['preferences' => ['monitors_view' => 'table']]);

    $response = $this
        ->actingAs($user)
        ->from(route('settings.show'))
        ->patch(route('settings.preferences.update'), [
            'monitors_view' => 'cards',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.show'));

    expect($user->refresh()->getPreference('monitors_view'))->toBe('cards');
});

test('preferences update preserves other preferences', function () {
    $user = User::factory()->create(['preferences' => ['other_pref' => 'value']]);

    $response = $this
        ->actingAs($user)
        ->patch(route('settings.preferences.update'), [
            'monitors_view' => 'table',
        ]);

    $response->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->getPreference('monitors_view'))->toBe('table');
    expect($user->getPreference('other_pref'))->toBe('value');
});

test('preferences update requires authentication', function () {
    $response = $this->patch(route('settings.preferences.update'), [
        'monitors_view' => 'table',
    ]);

    $response->assertRedirect(route('login'));
});
