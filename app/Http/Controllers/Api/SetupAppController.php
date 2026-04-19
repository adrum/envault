<?php

namespace App\Http\Controllers\Api;

use App\Models\App;
use App\Models\AppSetupToken;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class SetupAppController extends Controller
{
    public function __invoke(App $app, string $token): JsonResponse
    {
        $setupToken = AppSetupToken::where('app_id', $app->id)
            ->where('token', $token)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->first();

        if (!$setupToken) {
            return response()->json([
                'error' => 'Invalid or expired setup token.',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = $setupToken->user;

        if ($user->cannot('view', $app)) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 403);
        }

        $authToken = $user->createToken(uniqid())->plainTextToken;

        // If token is tied to an environment, only return that environment's variables
        if ($setupToken->environment_id) {
            $app->setRelation(
                'variables',
                $setupToken->environment->variables()->get()
            );
        } else {
            $app->load('variables');
        }

        $setupToken->delete();

        return response()->json([
            'authToken' => $authToken,
            'app' => $app,
        ]);
    }
}
