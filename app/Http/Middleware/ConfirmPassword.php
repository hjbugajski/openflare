<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;

/**
 * Requires a recently confirmed password like the framework middleware, but
 * pins where the user lands afterwards. For a non-GET request the framework
 * falls back to `UrlGenerator::previous()`, which trusts the Referer header
 * and passes an absolute value through untouched — so a request the attacker
 * can trigger decides the intended URL, and confirming the password sends the
 * owner wherever that header pointed. Anchoring it to the settings page keeps
 * that redirect target off the wire.
 */
class ConfirmPassword extends RequirePassword
{
    /**
     * @param  Request  $request
     * @param  string|null  $redirectToRoute
     * @param  string|int|null  $passwordTimeoutSeconds
     */
    public function handle($request, Closure $next, $redirectToRoute = null, $passwordTimeoutSeconds = null): mixed
    {
        if (! $request->isMethodSafe() && ! $request->expectsJson() && $this->shouldConfirmPassword($request, $passwordTimeoutSeconds)) {
            $request->session()->put('url.intended', route('settings.show'));

            return redirect()->route($redirectToRoute ?: 'password.confirm');
        }

        return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
    }
}
