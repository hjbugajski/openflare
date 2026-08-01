<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;

/**
 * Requires a recently confirmed password like the framework middleware, but
 * sends unconfirmed state-changing requests back to a GET route once the
 * password is confirmed. The framework stores the current URL as the intended
 * URL and replays it with GET, which would 405 on a POST/DELETE-only route.
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
