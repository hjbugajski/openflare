<?php

use App\Http\Controllers\Api\MonitorController as ApiMonitorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\NotifierController;
use App\Mail\MonitorStatusChanged;
use App\Mail\TestNotification;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\User;
use App\MonitorStatus;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Route;

if (config('app.mail_preview') && ! app()->isProduction()) {
    Route::prefix('mail-preview')
        ->name('mail-preview.')
        ->middleware(['auth', 'verified'])
        ->group(function () {
            Route::get('/monitor-down', function () {
                return new MonitorStatusChanged(
                    Monitor::factory()->make(),
                    MonitorCheck::factory()->make(),
                    MonitorStatus::Down
                );
            })->name('monitor-down');

            Route::get('/monitor-up', function () {
                return new MonitorStatusChanged(
                    Monitor::factory()->make(),
                    MonitorCheck::factory()->make(),
                    MonitorStatus::Up
                );
            })->name('monitor-up');

            Route::get('/verify-email', function () {
                $user = User::first();
                if (! $user) {
                    abort(404, 'No user found for preview');
                }

                return (new VerifyEmail)->toMail($user);
            })->name('verify-email');

            Route::get('/reset-password', function () {
                $user = User::first();
                if (! $user) {
                    abort(404, 'No user found for preview');
                }

                return (new ResetPassword('fake-token-for-preview'))->toMail($user);
            })->name('reset-password');

            Route::get('/test-notification', function () {
                return new TestNotification;
            })->name('test-notification');
        });
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::resource('monitors', MonitorController::class)
        ->middleware('throttle:monitors');
    Route::post('monitors/{monitor}/notifiers/{notifier}', [MonitorController::class, 'attachNotifier'])
        ->name('monitors.notifiers.attach')
        ->middleware('throttle:monitors');
    Route::delete('monitors/{monitor}/notifiers/{notifier}', [MonitorController::class, 'detachNotifier'])
        ->name('monitors.notifiers.detach')
        ->middleware('throttle:monitors');
    Route::resource('notifiers', NotifierController::class)
        ->except(['show'])
        ->middleware('throttle:notifications');
    Route::patch('notifiers/{notifier}/toggle', [NotifierController::class, 'toggle'])
        ->name('notifiers.toggle')
        ->middleware('throttle:notifications');
    Route::post('notifiers/test', [NotifierController::class, 'test'])
        ->name('notifiers.test')
        ->middleware('throttle:notifications');

    // API endpoints for real-time data
    Route::prefix('api')->name('api.')->middleware('throttle:api')->group(function () {
        Route::get('monitors/{monitor}', [ApiMonitorController::class, 'show'])->name('monitors.show');
        Route::get('monitors/{monitor}/checks', [ApiMonitorController::class, 'checks'])->name('monitors.checks');
        Route::get('monitors/{monitor}/rollups', [ApiMonitorController::class, 'rollups'])->name('monitors.rollups');
    });
});

require __DIR__.'/settings.php';
