<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::get('/login/{driver}', function (string $driver) {
    session()->put('socialite_remember', request()->boolean('remember'));

    return Inertia::location(Socialite::driver($driver)->redirect());
})->name('login.socialite.start');

Route::get('/login/{driver}/callback', App\Http\Controllers\Auth\SocialiteCallbackController::class)->name('login.socialite.callback');
