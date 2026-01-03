<?php

use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::patch('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('settings/password', [SettingsController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('settings.password.update');
    Route::patch('settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');
    Route::delete('settings', [SettingsController::class, 'destroy'])->name('settings.destroy');

    // Two-Factor Authentication
    Route::prefix('settings/two-factor')->middleware('password.confirm')->group(function () {
        Route::get('/enable', [TwoFactorController::class, 'enable'])->name('settings.two-factor.enable');
        Route::get('/setup', [TwoFactorController::class, 'setup'])->name('settings.two-factor.setup');
        Route::get('/confirm', [TwoFactorController::class, 'showConfirm'])->name('settings.two-factor.confirm');
        Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('settings.two-factor.confirm.store');
        Route::get('/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('settings.two-factor.recovery-codes');
        Route::post('/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('settings.two-factor.recovery-codes.regenerate');
        Route::get('/disable', [TwoFactorController::class, 'disable'])->name('settings.two-factor.disable');
    });
});
