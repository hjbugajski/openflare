<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SsrfGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL is safe and does not point to internal/private networks.
 * Prevents SSRF (Server-Side Request Forgery) attacks.
 */
class SafeUrl implements ValidationRule
{
    private const BLOCKED_HOSTNAMES = [
        'localhost',
        'metadata.google.internal',
        'metadata.goog',
        'kubernetes.default.svc',
        'kubernetes.default',
    ];

    /**
     * @param  (callable(string): list<string>)|null  $resolver  Resolves a hostname to IP
     *                                                           addresses; defaults to DNS. Injectable so tests never hit the network.
     */
    public function __construct(private $resolver = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The URL must be a string.');

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

        $ssrfGuard = new SsrfGuard;

        if (filter_var($hostForValidation, FILTER_VALIDATE_IP) !== false) {
            if ($ssrfGuard->isBlockedIp($hostForValidation)) {
                $fail('The URL must not point to private or internal networks.');

                return;
            }
        } else {
            $addresses = $this->resolve($host);

            // Fail closed. An empty answer means NXDOMAIN, a resolver failure,
            // or a host that is not a real name at all (a bare integer such as
            // 2130706433, or 0x7f000001, which curl would still dial as
            // 127.0.0.1). Passing here would skip the block-check entirely.
            if ($addresses === []) {
                $fail('The URL host could not be resolved.');

                return;
            }

            foreach ($addresses as $ip) {
                if ($ssrfGuard->isBlockedIp($ip)) {
                    $fail('The URL must not resolve to private or internal networks.');

                    return;
                }
            }
        }
    }

    /**
     * Resolve a hostname to every A and AAAA address it advertises. All of them
     * are checked: a host that mixes a public A record with an internal AAAA
     * record would otherwise slip through.
     *
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return ($this->resolver)($host);
        }

        // The two types are queried separately: a combined DNS_A|DNS_AAAA
        // lookup returns false outright when either type has no answer, so an
        // IPv4-only host such as api.github.com would look unresolvable.
        $records = array_merge(
            @dns_get_record($host, DNS_A) ?: [],
            @dns_get_record($host, DNS_AAAA) ?: [],
        );

        return array_values(array_filter(
            array_map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null, $records)
        ));
    }
}
