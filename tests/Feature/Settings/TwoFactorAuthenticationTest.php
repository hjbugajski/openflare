<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('account page displays two factor section', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('settings.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/index')
            ->where('twoFactorEnabled', false)
        );
});

test('account page shows two factor as enabled when user has 2fa', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('settings.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/index')
            ->where('twoFactorEnabled', true)
        );
});
