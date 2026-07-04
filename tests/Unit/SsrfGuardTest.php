<?php

declare(strict_types=1);

use App\Support\SsrfGuard;

function isBlockedIp(string $ip): bool
{
    return (new SsrfGuard)->isBlockedIp($ip);
}

describe('SsrfGuard - IPv4 blocked ranges', function () {
    it('blocks loopback (127.0.0.0/8)', function () {
        expect(isBlockedIp('127.0.0.1'))->toBeTrue();
        expect(isBlockedIp('127.1.2.3'))->toBeTrue();
    });

    it('blocks private class A/B/C (RFC 1918)', function () {
        expect(isBlockedIp('10.0.0.1'))->toBeTrue();
        expect(isBlockedIp('172.16.0.1'))->toBeTrue();
        expect(isBlockedIp('172.31.255.255'))->toBeTrue();
        expect(isBlockedIp('192.168.1.1'))->toBeTrue();
    });

    it('blocks link-local and cloud-metadata range (169.254.0.0/16)', function () {
        expect(isBlockedIp('169.254.169.254'))->toBeTrue(); // AWS/GCP/Azure metadata
        expect(isBlockedIp('169.254.0.1'))->toBeTrue();
    });

    it('blocks carrier-grade NAT (100.64.0.0/10)', function () {
        expect(isBlockedIp('100.64.0.1'))->toBeTrue();
        expect(isBlockedIp('100.127.255.255'))->toBeTrue();
    });

    it('blocks 0.0.0.0/8, multicast, and reserved ranges', function () {
        expect(isBlockedIp('0.0.0.1'))->toBeTrue();
        expect(isBlockedIp('224.0.0.1'))->toBeTrue();
        expect(isBlockedIp('240.0.0.1'))->toBeTrue();
    });
});

describe('SsrfGuard - IPv4 public addresses', function () {
    it('allows public IPs', function () {
        expect(isBlockedIp('8.8.8.8'))->toBeFalse();
        expect(isBlockedIp('1.1.1.1'))->toBeFalse();
    });

    it('allows edge-of-range public addresses', function () {
        expect(isBlockedIp('172.32.0.1'))->toBeFalse(); // just outside 172.16.0.0/12
        expect(isBlockedIp('100.128.0.1'))->toBeFalse(); // just outside 100.64.0.0/10
    });
});

describe('SsrfGuard - IPv6', function () {
    it('blocks loopback (::1)', function () {
        expect(isBlockedIp('::1'))->toBeTrue();
    });

    it('blocks link-local (fe80::/10)', function () {
        expect(isBlockedIp('fe80::1'))->toBeTrue();
    });

    it('blocks unique local (fc00::/7)', function () {
        expect(isBlockedIp('fc00::1'))->toBeTrue();
        expect(isBlockedIp('fd00::1'))->toBeTrue();
    });

    it('blocks IPv4-mapped addresses embedding a blocked IPv4', function () {
        expect(isBlockedIp('::ffff:127.0.0.1'))->toBeTrue();
        expect(isBlockedIp('::ffff:10.0.0.1'))->toBeTrue();
    });

    it('allows IPv4-mapped addresses embedding a public IPv4', function () {
        expect(isBlockedIp('::ffff:8.8.8.8'))->toBeFalse();
    });

    it('blocks 6to4 addresses embedding a blocked IPv4 (superset behavior noted in this plan)', function () {
        // 2002:0a00:0001:: embeds 10.0.0.1 (private RFC 1918)
        expect(isBlockedIp('2002:0a00:0001::'))->toBeTrue();
    });

    it('allows public IPv6 addresses', function () {
        expect(isBlockedIp('2606:4700:4700::1111'))->toBeFalse(); // Cloudflare DNS
    });

    it('blocks multicast (ff00::/8)', function () {
        expect(isBlockedIp('ff02::1'))->toBeTrue();
        expect(isBlockedIp('ff00::1'))->toBeTrue();
    });

    it('blocks NAT64 addresses embedding a blocked IPv4 (64:ff9b::/96)', function () {
        // 64:ff9b::a9fe:a9fe embeds 169.254.169.254 (link-local/metadata)
        expect(isBlockedIp('64:ff9b::a9fe:a9fe'))->toBeTrue();
    });

    it('allows NAT64 addresses embedding a public IPv4', function () {
        // 64:ff9b::808:808 embeds 8.8.8.8
        expect(isBlockedIp('64:ff9b::808:808'))->toBeFalse();
    });
});

describe('SsrfGuard - additional blocked addresses', function () {
    it('blocks 0.0.0.0', function () {
        expect(isBlockedIp('0.0.0.0'))->toBeTrue();
    });

    it('blocks IPv4-mapped metadata address', function () {
        expect(isBlockedIp('::ffff:169.254.169.254'))->toBeTrue();
    });
});
