<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Middleware\EnsureRegistrationIsOpen;
use App\Http\Responses\LogoutResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureRegistrationMiddleware();
        $this->configurePasswordResetMiddleware();
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => EnsureRegistrationIsOpen::isOpen(),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input(Fortify::username())));

            // The email-only bucket keeps the limiter effective when the client
            // IP is attacker-controlled (spoofed X-Forwarded-For behind a
            // trusted proxy, or a large address pool). Its window is
            // deliberately short: the route-level throttle counts every request
            // and is never cleared on success, so anyone who knows the account
            // email can hold the bucket full. A one-minute decay caps a
            // targeted lockout at roughly the attack's own duration while still
            // bottlenecking credential stuffing to 600 guesses an hour.
            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(10)->by('login-email:'.$email),
            ];
        });

        // Named limiters are keyed by limiter name plus bucket key, with no
        // route component, so `password.email` and `password.update` need
        // separate limiters or a failed request on one route would burn the
        // other route's budget.
        RateLimiter::for('reset-password', function (Request $request) {
            $email = $this->resetEmail($request);

            // Every accepted request sends real mail, so the hourly per-address
            // bucket is what caps a mail-bomb; the short pair bucket only
            // smooths out impatient retries.
            return [
                Limit::perMinute(2)->by($email.'|'.$request->ip()),
                Limit::perHour(5)->by('reset-email:'.$email),
            ];
        });

        RateLimiter::for('reset-password-update', function (Request $request) {
            // This route sends no mail, so the cap only has to bottleneck
            // online guessing of a long random token. It is deliberately
            // generous — mistyped password confirmations are the common case
            // and an hour-scale bucket here would lock the user out of their
            // own reset.
            return Limit::perMinute(5)->by($this->resetEmail($request).'|'.$request->ip());
        });
    }

    private function resetEmail(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input(Fortify::email())));
    }

    /**
     * Apply middleware to registration routes to prevent access when a user exists.
     */
    private function configureRegistrationMiddleware(): void
    {
        Route::matched(function ($event) {
            $routeName = $event->route->getName();

            if (in_array($routeName, ['register', 'register.store'])) {
                $event->route->middleware(EnsureRegistrationIsOpen::class);
            }
        });
    }

    /**
     * Throttle the password reset routes.
     *
     * Fortify only consults `fortify.limiters` for its login, two-factor,
     * passkey and verification routes, so the reset routes have to be throttled
     * here.
     */
    private function configurePasswordResetMiddleware(): void
    {
        $limiters = [
            'password.email' => 'reset-password',
            'password.update' => 'reset-password-update',
        ];

        Route::matched(function ($event) use ($limiters) {
            $limiter = $limiters[$event->route->getName()] ?? null;

            if ($limiter !== null) {
                $event->route->middleware('throttle:'.$limiter);
            }
        });
    }
}
