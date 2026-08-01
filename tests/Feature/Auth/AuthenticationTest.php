<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
});

test('auth pages have no-cache headers to prevent back button access', function () {
    $response = $this->get(route('login'));

    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store');
    expect($cacheControl)->toContain('no-cache');
    $response->assertHeader('Pragma', 'no-cache');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('no proxies are trusted by default', function () {
    expect(config('trustedproxy.proxies'))->toBeNull();
});

test('a spoofed x-forwarded-for does not shard the login rate limiter', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['X-Forwarded-For' => '203.0.113.9']);

    $response->assertTooManyRequests();
});

test('a configured trusted proxy makes x-forwarded-for the client ip', function () {
    config(['trustedproxy.proxies' => '*']);

    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '203.0.113.9'])), amount: 5);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['X-Forwarded-For' => '203.0.113.9'])->assertTooManyRequests();
});

test('login attempts from rotating source ips are rate limited by email', function () {
    $user = User::factory()->create();

    // Each attempt looks like a different client, so the email|ip bucket never
    // fills; only the email bucket can stop the spray.
    foreach (range(1, 20) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$i])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(302);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.21'])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

    $this->assertGuest();
});
