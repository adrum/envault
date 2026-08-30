<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Enums\UserRole;
use App\Support\OidcConnections;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteCallbackController
{
    public function __invoke(string $driver)
    {
        // OIDC connections are mirrored into services.* by the provider package,
        // but must only ever be completed by OidcCallbackController; this flow is
        // stateless and would skip their state, nonce and PKCE checks.
        abort_if(OidcConnections::isOidcDriver($driver), 404);

        abort_unless(filled(config("services.{$driver}.client_id")), 404);

        /** @phpstan-ignore-next-line */
        $social = Socialite::driver($driver)->stateless()->user();

        if (User::count() === 0) {
            $user = User::where('email', $social->getEmail())->first() ?? new User([
                'first_name' => explode(' ', $social->getName())[0],
                'last_name' => explode(' ', $social->getName(), 2)[1] ?? '',
                'email' => $social->getEmail(),
                'password' => null,
                'role' => UserRole::ADMIN,
            ]);
        } else {
            $user = User::where('email', $social->getEmail())->first();
            if (!$user) {
                abort(403, 'You are not authorized to access this application.');
            }
        }

        $user->save();

        $remembered = session()->pull('socialite_remember', false);

        Auth::guard()->loginUsingId($user->id, remember: $remembered);

        return redirect()->route('dashboard');
    }
}
