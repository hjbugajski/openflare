<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('responses include a report-only content security policy matching the Reverb config', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertHeader('Content-Security-Policy-Report-Only');

    $csp = $response->headers->get('Content-Security-Policy-Report-Only');

    $host = config('reverb.apps.apps.0.options.host');
    $port = config('reverb.apps.apps.0.options.port');
    $scheme = config('reverb.apps.apps.0.options.scheme') === 'https' ? 'wss' : 'ws';

    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("script-src 'self'");
    expect($csp)->toContain("style-src 'self' 'unsafe-inline' https://fonts.googleapis.com");
    expect($csp)->toContain("font-src 'self' https://fonts.gstatic.com");
    expect($csp)->toContain("img-src 'self'");
    expect($csp)->toContain("connect-src 'self' {$scheme}://{$host}:{$port}");
    expect($csp)->toContain("base-uri 'self'");
    expect($csp)->toContain("form-action 'self'");
    expect($csp)->toContain("frame-ancestors 'self'");
});

test('the CSP connect-src matches the Reverb external endpoint, not APP_URL', function () {
    config([
        'app.url' => 'http://localhost:8000',
        'reverb.apps.apps.0.options.host' => 'localhost',
        'reverb.apps.apps.0.options.port' => 8080,
        'reverb.apps.apps.0.options.scheme' => 'http',
    ]);

    $response = $this->get(route('login'));
    $csp = $response->headers->get('Content-Security-Policy-Report-Only');

    // APP_URL is on port 8000, but Reverb's external endpoint is on 8080 —
    // the CSP must reflect the endpoint the browser actually connects to.
    expect($csp)->toContain("connect-src 'self' ws://localhost:8080");
    expect($csp)->not->toContain('ws://localhost:8000');
});

test('the settings page is not stored by the browser cache', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings.show'));

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('the two factor setup page is not stored by the browser cache', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.two-factor.enable'));

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.setup'));

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('logging out clears the Inertia history state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => expect($page->toArray())->toHaveKey('clearHistory', true));
});

test('page props are encrypted in the browser history by default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.show'))
        ->assertInertia(fn (Assert $page) => expect($page->toArray())->toHaveKey('encryptHistory', true));
});
