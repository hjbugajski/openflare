<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Proxies whose X-Forwarded-* headers may be trusted: a comma-separated list
    | of IPs/CIDRs, or "*" for every proxy. Null (the default) trusts none, so a
    | spoofed X-Forwarded-For cannot change the client IP the rate limiters key
    | on. Widen this only when a proxy you control terminates connections in
    | front of the app and overwrites those headers.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
