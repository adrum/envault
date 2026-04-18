<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Auth\SessionGuard;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        try {
            $guard = Auth::guard('web');
            // Check if guard has logoutCurrentDevice method
            if ($guard instanceof SessionGuard) {
                $guard->logoutCurrentDevice();
            } else {
                $guard->logout();
            }
        } catch (\Exception $e) {
            auth()->logout();
        }

        $persistedSessionKeys = [];

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            // @phpstan-ignore-next-line
            foreach ($persistedSessionKeys as $key => $value) {
                $request->session()->put($key, $value);
            }
        }

        // Handle SSO Redirect
        $redirectUrl = request()->get('post_logout_redirect_uri');

        if ($redirectUrl) {
            return redirect()->away($redirectUrl);
        }

        return redirect()->intended();
    }
}
