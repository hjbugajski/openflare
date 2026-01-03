<?php

declare(strict_types=1);

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->user = User::factory()->withoutTwoFactor()->create();
});

test('enabling 2FA redirects to setup page', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.enable'))
        ->assertRedirect(route('settings.two-factor.setup'));

    $this->user->refresh();
    expect($this->user->two_factor_secret)->not->toBeNull();
    expect($this->user->two_factor_confirmed_at)->toBeNull();
});

test('setup page shows QR code and secret key', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.enable'));

    $this->user->refresh();

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.setup'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/two-factor/setup')
            ->has('qrCodeSvg')
            ->has('secretKey')
        );
});

test('setup page redirects to account if 2FA not enabled', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.setup'))
        ->assertRedirect(route('settings.show'));
});

test('setup page redirects to account if 2FA already confirmed', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.setup'))
        ->assertRedirect(route('settings.show'));
});

test('confirm page can be viewed during setup', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.enable'));

    $this->user->refresh();

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.confirm'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/two-factor/confirm')
        );
});

test('confirm page redirects to account if 2FA not enabled', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.confirm'))
        ->assertRedirect(route('settings.show'));
});

test('confirm page redirects to account if 2FA already confirmed', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.confirm'))
        ->assertRedirect(route('settings.show'));
});

test('valid code confirms 2FA and redirects to recovery codes', function () {
    // Enable 2FA first
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.enable'));

    $this->user->refresh();

    // Generate valid TOTP code
    $google2fa = app(Google2FA::class);
    $secret = decrypt($this->user->two_factor_secret);
    $validCode = $google2fa->getCurrentOtp($secret);

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.confirm.store'), ['code' => $validCode])
        ->assertRedirect(route('settings.two-factor.recovery-codes'));

    $this->user->refresh();
    expect($this->user->two_factor_confirmed_at)->not->toBeNull();
});

test('invalid code does not confirm 2FA', function () {
    // Enable 2FA first
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.enable'));

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.confirm.store'), ['code' => '000000']);

    $this->user->refresh();
    expect($this->user->two_factor_confirmed_at)->toBeNull();
});

test('recovery codes page shows codes after 2FA confirmation', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'show_recovery_codes' => true,
        ])
        ->get(route('settings.two-factor.recovery-codes'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/two-factor/recovery-codes')
            ->has('recoveryCodes')
        );
});

test('recovery codes page redirects to account if 2FA not confirmed', function () {
    $this->actingAs($this->user)
        ->withSession([
            'auth.password_confirmed_at' => time(),
            'show_recovery_codes' => true,
        ])
        ->get(route('settings.two-factor.recovery-codes'))
        ->assertRedirect(route('settings.show'));
});

test('recovery codes page redirects to account without session flash', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.recovery-codes'))
        ->assertRedirect(route('settings.show'));
});

test('recovery codes can be regenerated', function () {
    $user = User::factory()->withTwoFactor()->create();
    $originalCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.recovery-codes.regenerate'))
        ->assertRedirect(route('settings.two-factor.recovery-codes'));

    $user->refresh();
    $newCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

    expect($newCodes)->not->toBe($originalCodes);
});

test('2FA can be disabled', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.disable'))
        ->assertRedirect(route('settings.show'));

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();
});
