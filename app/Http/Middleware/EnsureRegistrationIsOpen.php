<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $lock = Cache::lock('registration_check', 10);

        try {
            $lock->block(5);

            if (User::exists()) {
                abort(404);
            }

            return $next($request);
        } catch (LockTimeoutException) {
            abort(429, 'Too many registration attempts. Please try again.');
        } finally {
            $lock->release();
        }
    }

    public static function isOpen(): bool
    {
        return ! User::exists();
    }
}
