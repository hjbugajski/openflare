<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\BackfillMissingRollups;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureAuthNotifications();
        $this->validateProductionSecuritySettings();
        $this->computeMissingRollups();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('monitors', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->uuid ?: $request->ip());
        });

        RateLimiter::for('notifications', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->uuid ?: $request->ip());
        });
    }

    protected function configureAuthNotifications(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Verify your email address')
                ->line('Click the button below to verify your email address.')
                ->action('Verify email address', $url)
                ->withSymfonyMessage(function (Email $message) {
                    $message->getHeaders()->addTextHeader('X-Entity-Ref-ID', Str::uuid()->toString());
                });
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Reset your password')
                ->line('You are receiving this email because we received a password reset request for your account.')
                ->action('Reset password', $url)
                ->line('This password reset link will expire in '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
                ->line('If you did not request a password reset, no further action is required.')
                ->withSymfonyMessage(function (Email $message) {
                    $message->getHeaders()->addTextHeader('X-Entity-Ref-ID', Str::uuid()->toString());
                });
        });
    }

    protected function validateProductionSecuritySettings(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (config('app.debug')) {
            Log::warning('Security: Debug mode is enabled in production. This exposes sensitive information.');
        }

        $reverbKey = config('reverb.apps.apps.0.key');
        $reverbSecret = config('reverb.apps.apps.0.secret');

        if (empty($reverbKey) || empty($reverbSecret)) {
            Log::warning('Security: Reverb WebSocket credentials are not configured. Real-time features may not work.');
        } elseif ($reverbKey === 'openflare-key' || $reverbSecret === 'openflare-secret') {
            Log::warning('Security: Reverb WebSocket credentials are using default values. Please generate secure secrets.');
        }
    }

    protected function computeMissingRollups(): void
    {
        if (! app()->runningInConsole() || app()->runningUnitTests()) {
            return;
        }

        // Skip during infrastructure commands
        $command = $_SERVER['argv'][1] ?? '';
        $skipCommands = ['migrate', 'config:', 'route:', 'view:', 'event:', 'cache:', 'key:', 'storage:'];

        foreach ($skipCommands as $skip) {
            if (str_starts_with($command, $skip)) {
                return;
            }
        }

        app()->booted(function () {
            try {
                app(BackfillMissingRollups::class)->handle();
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
