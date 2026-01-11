<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecomputeUserRollups;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/index', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication() && ! is_null($user->two_factor_confirmed_at),
        ]);
    }

    public function updateProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return redirect()->route('settings.show')->with('status', 'profile-updated');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return back()->with('status', 'password-updated');
    }

    public function updatePreferences(Request $request, RecomputeUserRollups $recomputeUserRollups): RedirectResponse
    {
        $validated = $request->validate([
            'monitors_view' => ['sometimes', 'string', 'in:cards,table'],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ]);

        $user = $request->user();
        $previousTimezone = $user->getPreference('timezone', config('app.timezone'));

        foreach ($validated as $key => $value) {
            $user->setPreference($key, $value);
        }

        $user->save();

        if (array_key_exists('timezone', $validated) && $validated['timezone'] !== $previousTimezone) {
            $recomputeUserRollups->handle($user, $validated['timezone']);
        }

        return back()->with('status', 'preferences-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
