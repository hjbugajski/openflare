<?php

test('responses include a report-only content security policy', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertHeader('Content-Security-Policy-Report-Only');

    $csp = $response->headers->get('Content-Security-Policy-Report-Only');

    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("connect-src 'self' ws://localhost:8000");
});
