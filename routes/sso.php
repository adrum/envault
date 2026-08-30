<?php

use Inertia\Inertia;
use App\Support\OidcConnections;
use Illuminate\Support\Facades\Route;

/*
 * Generic OpenID Connect sign in, one connection per issuer configured in
 * config/oidc.php. Registered ahead of the catch-all Socialite routes below so
 * a connection name is never mistaken for a Socialite driver. Unknown
 * connections 404 rather than being constrained in the route definition, so
 * that a cached route table survives a change to the OIDC configuration.
 */
Route::get('/login/oidc/{connection}', function (string $connection) {
    abort_unless(OidcConnections::has($connection), 404);

    session()->put('oidc_remember', request()->boolean('remember'));

    return Inertia::location(Socialite::driver(OidcConnections::driver($connection))->redirect());
})->name('login.oidc.start');

Route::get('/login/oidc/{connection}/callback', App\Http\Controllers\Auth\OidcCallbackController::class)
    ->name('login.oidc.callback');

/*
 * The catch-all Socialite routes. A driver is only accepted once it has been
 * configured, so an unknown /login/anything answers 404 instead of handing the
 * string to Socialite and raising a 500 on every stray URL.
 */
Route::get('/login/{driver}', function (string $driver) {
    abort_if(OidcConnections::isOidcDriver($driver), 404);
    abort_unless(filled(config("services.{$driver}.client_id")), 404);

    session()->put('socialite_remember', request()->boolean('remember'));

    return Inertia::location(Socialite::driver($driver)->redirect());
})->where('driver', '[a-z0-9_-]+')->name('login.socialite.start');

Route::get('/login/{driver}/callback', App\Http\Controllers\Auth\SocialiteCallbackController::class)
    ->where('driver', '[a-z0-9_-]+')
    ->name('login.socialite.callback');
