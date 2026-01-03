<?php

declare(strict_types=1);

use App\Models\User;

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
