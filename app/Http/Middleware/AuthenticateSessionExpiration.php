<?php

namespace App\Http\Middleware;

use Sentry;
use Closure;
use Inertia\Inertia;
use Sentry\State\Scope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

class AuthenticateSessionExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (\Symfony\Component\HttpFoundation\Response|Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            Sentry::configureScope(
                fn (Scope $scope) => $scope->setUser(array_filter([
                    'id' => $user->id,
                ]))
            );
        }

        if ($user) {
            /** @var SessionGuard $guard */
            $guard = auth()->guard();

            Inertia::share('session', [
                'expiry' => $request->user() ? now()->addMinutes(config('session.lifetime'))->timestamp : null,
                'remembered' => $request->hasCookie($guard->getRecallerName()),
                'server_time' => now()->timestamp,
            ]);
        }

        $response = $next($request);

        if ($user && method_exists($response, 'header')) {
            $response->header('x-session-expiration', now()->addMinutes(config('session.lifetime'))->timestamp);
            $response->header('x-server-time', now()->timestamp);
        }

        return $response;
    }
}
