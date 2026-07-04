<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy-Report-Only', $this->contentSecurityPolicy());

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $parsedUrl = parse_url((string) config('app.url')) ?: [];
        $isHttps = ($parsedUrl['scheme'] ?? 'http') === 'https';

        // Mirror the same Reverb external endpoint HandleInertiaRequests
        // shares with the frontend, so the CSP always matches where the
        // browser actually connects (see config/reverb.php).
        $reverbHost = (string) config('reverb.apps.apps.0.options.host', $parsedUrl['host'] ?? 'localhost');
        $reverbPort = (int) config('reverb.apps.apps.0.options.port', 443);
        $reverbScheme = config('reverb.apps.apps.0.options.scheme', $isHttps ? 'https' : 'http');
        $wsScheme = $reverbScheme === 'https' ? 'wss' : 'ws';

        $directives = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self'",
            "connect-src 'self' {$wsScheme}://{$reverbHost}:{$reverbPort}",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directives);
    }
}
