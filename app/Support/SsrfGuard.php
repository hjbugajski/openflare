<?php

declare(strict_types=1);

namespace App\Support;

class SsrfGuard
{
    private const BLOCKED_IPV4_RANGES = [
        '10.0.0.0/8',        // Private (RFC 1918)
        '172.16.0.0/12',     // Private (RFC 1918)
        '192.168.0.0/16',    // Private (RFC 1918)
        '127.0.0.0/8',       // Loopback
        '169.254.0.0/16',    // Link-local & AWS/Azure metadata
        '0.0.0.0/8',         // "This" network
        '100.64.0.0/10',     // Carrier-grade NAT (RFC 6598)
        '192.0.0.0/24',      // IETF Protocol Assignments
        '192.0.2.0/24',      // TEST-NET-1 (documentation)
        '198.51.100.0/24',   // TEST-NET-2 (documentation)
        '203.0.113.0/24',    // TEST-NET-3 (documentation)
        '224.0.0.0/4',       // Multicast
        '240.0.0.0/4',       // Reserved for future use
        '255.255.255.255/32', // Broadcast
    ];

    public function isBlockedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isBlockedIpv4($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->isBlockedIpv6($ip);
        }

        return false;
    }

    public function isBlockedIpv4(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        foreach (self::BLOCKED_IPV4_RANGES as $range) {
            [$subnet, $bits] = explode('/', $range);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - (int) $bits);

            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }

        return false;
    }

    public function isBlockedIpv6(string $ip): bool
    {
        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        $hex = bin2hex($packed);

        // Loopback (::1)
        if ($hex === '00000000000000000000000000000001') {
            return true;
        }

        // Unspecified (::)
        if ($hex === '00000000000000000000000000000000') {
            return true;
        }

        // Link-local (fe80::/10)
        if (str_starts_with($hex, 'fe8') || str_starts_with($hex, 'fe9') ||
            str_starts_with($hex, 'fea') || str_starts_with($hex, 'feb')) {
            return true;
        }

        // Unique local (fc00::/7)
        $firstByte = hexdec(substr($hex, 0, 2));
        if ($firstByte >= 0xFC && $firstByte <= 0xFD) {
            return true;
        }

        // Multicast (ff00::/8)
        if ($firstByte === 0xFF) {
            return true;
        }

        // IPv4-mapped (::ffff:0:0/96) - check embedded IPv4
        if (str_starts_with($hex, '00000000000000000000ffff')) {
            $ipv4Hex = substr($hex, 24, 8);
            $ipv4 = long2ip((int) hexdec($ipv4Hex));

            return $this->isBlockedIpv4($ipv4);
        }

        // 6to4 addresses (2002::/16) - check embedded IPv4
        if (str_starts_with($hex, '2002')) {
            $ipv4Hex = substr($hex, 4, 8);
            $ipv4 = long2ip((int) hexdec($ipv4Hex));

            return $this->isBlockedIpv4($ipv4);
        }

        // NAT64 well-known prefix (64:ff9b::/96) - check embedded IPv4
        if (str_starts_with($hex, '0064ff9b') && substr($hex, 8, 16) === str_repeat('0', 16)) {
            $ipv4Hex = substr($hex, 24, 8);
            $ipv4 = long2ip((int) hexdec($ipv4Hex));

            return $this->isBlockedIpv4($ipv4);
        }

        return false;
    }
}
