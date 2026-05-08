<?php

namespace App\Http\Controllers\Settings;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;

class SecurityController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return Features::hasSecurityFeatures()
            && (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword') || Features::optionEnabled(Features::passkeys(), 'confirmPassword'))
                ? [new Middleware('password.confirm', only: ['edit'])]
                : [];
    }

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canUpdatePassword' => Features::canUpdatePasswords(),
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => $request->user()->passkeys()->orderBy('created_at', 'desc')->get(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        if (Features::canManagePasskeys()) {
            $request->ensureStateIsValid();

            $props['passkeysEnabled'] = $request->user()->hasPasskeysEnabled();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::passkeys(), 'confirm');
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        toastSuccess('Password updated.');

        return back();
    }

    /**
     * Update the user's passkey.
     */
    public function updatePasskey(Request $request, Passkey $passkey): RedirectResponse
    {
        if ($request->user()->id != $passkey->user_id) {
            toastError(__('Passkey not found.'));

            return back();
        }

        $request->validate([
            'name' => ['required', 'min:3', 'max:255'],
        ]);

        $passkey->update([
            'name' => $request->name,
        ]);

        toastSuccess(__('Passkey updated.'));

        return back();
    }
}
