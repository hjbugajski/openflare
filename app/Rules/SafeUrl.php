<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL is safe and does not point to internal/private networks.
 * Prevents SSRF (Server-Side Request Forgery) attacks.
 */
class SafeUrl implements ValidationRule
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

    private const BLOCKED_HOSTNAMES = [
        'localhost',
        'metadata.google.internal',
        'metadata.goog',
        'kubernetes.default.svc',
        'kubernetes.default',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.safe_url.string')->translate();

            return;
        }

        $parsed = parse_url($value);

        if ($parsed === false || ! isset($parsed['scheme']) || ! isset($parsed['host'])) {
            $fail('The URL must be valid.');

            return;
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The URL must use HTTP or HTTPS protocol.');

            return;
        }

        $host = strtolower($parsed['host']);

        $hostForValidation = $host;
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $hostForValidation = substr($host, 1, -1);
        }

        foreach (self::BLOCKED_HOSTNAMES as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                $fail('The URL must not point to internal services.');

                return;
            }
        }

        if (filter_var($hostForValidation, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($this->isBlockedIpv4($hostForValidation)) {
                $fail('The URL must not point to private or internal networks.');

                return;
            }
        } elseif (filter_var($hostForValidation, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($this->isBlockedIpv6($hostForValidation)) {
                $fail('The URL must not point to private or internal networks.');

                return;
            }
        } else {
            $ip = gethostbyname($host);

            if ($ip !== $host) {
                if ($this->isBlockedIpv4($ip)) {
                    $fail('The URL must not resolve to private or internal networks.');

                    return;
                }
            }
        }
    }

    private function isBlockedIpv4(string $ip): bool
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

    private function isBlockedIpv6(string $ip): bool
    {
        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        $hex = bin2hex($packed);

        if ($hex === '00000000000000000000000000000001') {
            return true;
        }

        if ($hex === '00000000000000000000000000000000') {
            return true;
        }

        if (str_starts_with($hex, 'fe8') || str_starts_with($hex, 'fe9') ||
            str_starts_with($hex, 'fea') || str_starts_with($hex, 'feb')) {
            return true;
        }

        $firstByte = hexdec(substr($hex, 0, 2));
        if ($firstByte >= 0xFC && $firstByte <= 0xFD) {
            return true;
        }

        if (str_starts_with($hex, '00000000000000000000ffff')) {
            $ipv4Hex = substr($hex, 24, 8);
            $ipv4 = long2ip((int) hexdec($ipv4Hex));

            return $this->isBlockedIpv4($ipv4);
        }

        return false;
    }
}
