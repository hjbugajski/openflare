<?php

declare(strict_types=1);

use App\Rules\SafeUrl;

function validateUrl(string $url): bool
{
    $rule = new SafeUrl;
    $passed = true;

    $rule->validate('url', $url, function () use (&$passed) {
        $passed = false;
    });

    return $passed;
}

describe('SafeUrl Rule - Blocked URLs', function () {
    it('blocks localhost', function () {
        expect(validateUrl('http://localhost/'))->toBeFalse();
        expect(validateUrl('http://localhost:8080/'))->toBeFalse();
        expect(validateUrl('https://localhost/path'))->toBeFalse();
    });

    it('blocks loopback IP (127.0.0.0/8)', function () {
        expect(validateUrl('http://127.0.0.1/'))->toBeFalse();
        expect(validateUrl('http://127.0.0.1:3000/'))->toBeFalse();
        expect(validateUrl('http://127.1.2.3/'))->toBeFalse();
    });

    it('blocks private class A (10.0.0.0/8)', function () {
        expect(validateUrl('http://10.0.0.1/'))->toBeFalse();
        expect(validateUrl('http://10.255.255.255/'))->toBeFalse();
        expect(validateUrl('http://10.1.2.3:8080/path'))->toBeFalse();
    });

    it('blocks private class B (172.16.0.0/12)', function () {
        expect(validateUrl('http://172.16.0.1/'))->toBeFalse();
        expect(validateUrl('http://172.31.255.255/'))->toBeFalse();
        expect(validateUrl('http://172.20.0.1/'))->toBeFalse();
    });

    it('blocks private class C (192.168.0.0/16)', function () {
        expect(validateUrl('http://192.168.0.1/'))->toBeFalse();
        expect(validateUrl('http://192.168.1.1/'))->toBeFalse();
        expect(validateUrl('http://192.168.255.255/'))->toBeFalse();
    });

    it('blocks link-local / AWS metadata (169.254.0.0/16)', function () {
        expect(validateUrl('http://169.254.169.254/'))->toBeFalse();
        expect(validateUrl('http://169.254.169.254/latest/meta-data/'))->toBeFalse();
        expect(validateUrl('http://169.254.0.1/'))->toBeFalse();
    });

    it('blocks cloud metadata hostnames', function () {
        expect(validateUrl('http://metadata.google.internal/'))->toBeFalse();
        expect(validateUrl('http://metadata.goog/'))->toBeFalse();
    });

    it('blocks kubernetes hostnames', function () {
        expect(validateUrl('http://kubernetes.default.svc/'))->toBeFalse();
        expect(validateUrl('http://kubernetes.default/'))->toBeFalse();
    });

    it('blocks non-http/https schemes', function () {
        expect(validateUrl('file:///etc/passwd'))->toBeFalse();
        expect(validateUrl('ftp://example.com/'))->toBeFalse();
        expect(validateUrl('gopher://example.com/'))->toBeFalse();
        expect(validateUrl('dict://example.com/'))->toBeFalse();
    });

    it('blocks 0.0.0.0/8 range', function () {
        expect(validateUrl('http://0.0.0.0/'))->toBeFalse();
        expect(validateUrl('http://0.0.0.1/'))->toBeFalse();
    });

    it('blocks carrier-grade NAT (100.64.0.0/10)', function () {
        expect(validateUrl('http://100.64.0.1/'))->toBeFalse();
        expect(validateUrl('http://100.127.255.255/'))->toBeFalse();
    });

    it('blocks multicast (224.0.0.0/4)', function () {
        expect(validateUrl('http://224.0.0.1/'))->toBeFalse();
        expect(validateUrl('http://239.255.255.255/'))->toBeFalse();
    });
});

describe('SafeUrl Rule - Allowed URLs', function () {
    it('allows valid public HTTPS URLs', function () {
        expect(validateUrl('https://google.com/'))->toBeTrue();
        expect(validateUrl('https://example.com/path/to/resource'))->toBeTrue();
        expect(validateUrl('https://api.github.com/repos'))->toBeTrue();
    });

    it('allows valid public HTTP URLs', function () {
        expect(validateUrl('http://example.com/'))->toBeTrue();
        expect(validateUrl('http://httpbin.org/get'))->toBeTrue();
    });

    it('allows URLs with ports', function () {
        expect(validateUrl('https://example.com:443/'))->toBeTrue();
        expect(validateUrl('http://example.com:8080/api'))->toBeTrue();
    });

    it('allows URLs with query strings', function () {
        expect(validateUrl('https://example.com/search?q=test'))->toBeTrue();
        expect(validateUrl('https://api.example.com/v1/users?page=1&limit=10'))->toBeTrue();
    });

    it('allows public IP addresses', function () {
        // Google DNS
        expect(validateUrl('http://8.8.8.8/'))->toBeTrue();
        // Cloudflare DNS
        expect(validateUrl('http://1.1.1.1/'))->toBeTrue();
    });

    it('allows edge of private ranges (just outside)', function () {
        // Just outside 172.16.0.0/12 (172.32.0.0 is public)
        expect(validateUrl('http://172.32.0.1/'))->toBeTrue();
        // Just outside 100.64.0.0/10 (100.128.0.0 is public)
        expect(validateUrl('http://100.128.0.1/'))->toBeTrue();
    });
});

describe('SafeUrl Rule - Invalid URLs', function () {
    it('rejects malformed URLs', function () {
        expect(validateUrl('not-a-url'))->toBeFalse();
        expect(validateUrl('://missing-scheme.com'))->toBeFalse();
        expect(validateUrl(''))->toBeFalse();
    });

    it('rejects URLs without host', function () {
        expect(validateUrl('http://'))->toBeFalse();
        expect(validateUrl('https://'))->toBeFalse();
    });
});

describe('SafeUrl Rule - IPv6', function () {
    it('blocks IPv6 loopback', function () {
        expect(validateUrl('http://[::1]/'))->toBeFalse();
    });

    it('blocks IPv6 link-local', function () {
        expect(validateUrl('http://[fe80::1]/'))->toBeFalse();
    });

    it('blocks IPv6 unique local addresses', function () {
        expect(validateUrl('http://[fc00::1]/'))->toBeFalse();
        expect(validateUrl('http://[fd00::1]/'))->toBeFalse();
    });
});
