<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class TwoFactorController extends Controller
{
    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $enable($request->user(), false);

        return redirect()->route('settings.two-factor.setup');
    }

    public function setup(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Must have 2FA secret but not confirmed to view setup
        if (is_null($user->two_factor_secret)) {
            return redirect()->route('settings.show');
        }

        if (! is_null($user->two_factor_confirmed_at)) {
            return redirect()->route('settings.show');
        }

        return Inertia::render('settings/two-factor/setup', [
            'qrCodeSvg' => $user->twoFactorQrCodeSvg(),
            'secretKey' => decrypt($user->two_factor_secret),
            'setupKey' => $user->twoFactorQrCodeUrl(),
        ]);
    }

    public function showConfirm(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Must have 2FA secret but not confirmed
        if (is_null($user->two_factor_secret)) {
            return redirect()->route('settings.show');
        }

        if (! is_null($user->two_factor_confirmed_at)) {
            return redirect()->route('settings.show');
        }

        return Inertia::render('settings/two-factor/confirm');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $confirm($request->user(), $request->input('code'));

        session()->flash('show_recovery_codes', true);

        return redirect()->route('settings.two-factor.recovery-codes');
    }

    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Must have 2FA fully enabled and confirmed
        if (! $user->hasEnabledTwoFactorAuthentication() || is_null($user->two_factor_confirmed_at)) {
            return redirect()->route('settings.show');
        }

        // Only show codes if they were just generated (security measure)
        if (! session()->has('show_recovery_codes')) {
            return redirect()->route('settings.show');
        }

        return Inertia::render('settings/two-factor/recovery-codes', [
            'recoveryCodes' => json_decode(decrypt($user->two_factor_recovery_codes), true),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $generate($request->user());

        session()->flash('show_recovery_codes', true);

        return redirect()->route('settings.two-factor.recovery-codes');
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $disable($request->user());

        return redirect()->route('settings.show');
    }
}
