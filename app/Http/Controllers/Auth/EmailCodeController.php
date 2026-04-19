<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Inertia\Inertia;
use App\Models\AuthRequest;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Notifications\AuthRequestedNotification;

class EmailCodeController extends Controller
{
    public function create(Request $request)
    {
        $email = $request->session()->get('auth_email');

        if ($email) {
            return Inertia::render('auth/email-code', [
                'step' => 'code',
                'email' => $email,
                'passwordAuthEnabled' => Features::canUpdatePasswords(),
            ]);
        }

        return Inertia::render('auth/email-code', [
            'step' => 'email',
            'passwordAuthEnabled' => Features::canUpdatePasswords(),
        ]);
    }

    public function requestCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return redirect()->route('auth.email-code')
                ->withErrors(['email' => 'The selected email is invalid.']);
        }

        // Delete any existing auth requests for this user
        AuthRequest::where('user_id', $user->id)->delete();

        // Generate a random code and store its hash
        $code = Str::random(16);

        AuthRequest::create([
            'user_id' => $user->id,
            'token' => Hash::make($code),
        ]);

        // Send the plaintext code via email
        $user->notify(new AuthRequestedNotification($code));

        // Store email in session so the code step renders on GET
        $request->session()->put('auth_email', $validated['email']);

        return redirect()->route('auth.email-code');
    }

    public function verifyCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        $error = "This code is invalid. Please check it's what we sent you.";

        if (!$user) {
            return redirect()->route('auth.email-code')
                ->withErrors(['code' => $error]);
        }

        $authRequest = AuthRequest::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$authRequest || !Hash::check($validated['code'], $authRequest->token)) {
            return redirect()->route('auth.email-code')
                ->withErrors(['code' => $error]);
        }

        // Clean up
        AuthRequest::where('user_id', $user->id)->delete();
        $request->session()->forget('auth_email');

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Log in
        Auth::login($user);

        return redirect()->intended('/apps');
    }
}
