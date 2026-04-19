<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class VaultTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Check X-Vault-Token header first, fall back to Authorization: Bearer
        $token = $request->header('X-Vault-Token') ?? $request->bearerToken();

        if (!$token) {
            return response()->json(['errors' => ['permission denied']], 403);
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return response()->json(['errors' => ['permission denied']], 403);
        }

        /** @var ?\App\Models\User $user */
        $user = $accessToken->tokenable;

        if (!$user) {
            return response()->json(['errors' => ['permission denied']], 403);
        }

        // Update last used timestamp
        $accessToken->forceFill(['last_used_at' => now()])->save();

        // Set the authenticated user
        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
