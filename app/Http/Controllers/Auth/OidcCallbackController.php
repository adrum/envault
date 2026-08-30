<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Enums\UserRole;
use App\Support\OidcConnections;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class OidcCallbackController
{
    public function __invoke(string $connection)
    {
        abort_unless(OidcConnections::has($connection), 404);

        $social = Socialite::driver(OidcConnections::driver($connection))->user();

        // The id_token has already been validated by the provider (signature,
        // iss, aud, azp, exp, nonce, at_hash) before we get here.
        $sub = (string) $social->getId();
        $email = (string) $social->getEmail();

        /** @var array<string, mixed> $claims */
        $claims = $social instanceof SocialiteUser ? $social->getRaw() : [];

        if (blank($sub) || blank($email)) {
            abort(403, 'Your identity provider did not return the claims required to sign in.');
        }

        // `sub` is the only claim the issuer promises is stable, so it is what
        // we match on. Email is used to link an existing account on first sign
        // in, and to bootstrap the very first user of a fresh installation.
        $user = User::query()
            ->where('oidc_provider', $connection)
            ->where('oidc_sub', $sub)
            ->first()
            ?? User::query()->where('email', $email)->first();

        if (!$user) {
            if (User::count() > 0) {
                abort(403, 'You are not authorized to access this application.');
            }

            $user = new User([
                'first_name' => $this->firstName($claims, (string) $social->getName()),
                'last_name' => $this->lastName($claims, (string) $social->getName()),
                'email' => $email,
                'password' => null,
                'role' => UserRole::ADMIN,
            ]);
        }

        $user->oidc_provider = $connection;
        $user->oidc_sub = $sub;
        $user->save();

        $remembered = session()->pull('oidc_remember', false);

        Auth::guard()->loginUsingId($user->id, remember: $remembered);

        return redirect()->route('dashboard');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function firstName(array $claims, string $name): string
    {
        return (string) ($claims['given_name'] ?? explode(' ', $name)[0]);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function lastName(array $claims, string $name): string
    {
        return (string) ($claims['family_name'] ?? explode(' ', $name, 2)[1] ?? '');
    }
}
