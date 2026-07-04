<?php

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
