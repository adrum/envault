<?php

namespace App\Http\Controllers\Api;

use App\Models\App;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class VaultController extends Controller
{
    /**
     * GET /v1/sys/health
     * Health check endpoint for ESO connectivity validation.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'initialized' => true,
            'sealed' => false,
            'standby' => false,
            'performance_standby' => false,
            'replication_performance_mode' => 'disabled',
            'replication_dr_mode' => 'disabled',
            'server_time_utc' => now()->timestamp,
            'version' => '1.15.0',
            'cluster_name' => 'envault',
        ]);
    }

    /**
     * GET /v1/auth/token/lookup-self
     * Token validation endpoint used by ESO for auth checks.
     */
    public function tokenLookup(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'accessor' => '',
                'creation_time' => $user->created_at->timestamp,
                'creation_ttl' => 0,
                'display_name' => $user->name,
                'entity_id' => (string) $user->id,
                'expire_time' => null,
                'explicit_max_ttl' => 0,
                'id' => 'envault-token',
                'meta' => [
                    'email' => $user->email,
                ],
                'num_uses' => 0,
                'orphan' => true,
                'path' => 'auth/token/create',
                'policies' => ['default'],
                'renewable' => false,
                'ttl' => 0,
                'type' => 'service',
            ],
        ]);
    }

    /**
     * GET /v1/secret/data/{path}
     * Read secrets in Vault KV v2 format.
     * Path: {app-slug}/{env-type-name}
     */
    public function readSecret(Request $request, string $path): JsonResponse
    {
        $segments = explode('/', $path, 2);

        if (count($segments) !== 2) {
            return response()->json(['errors' => ['invalid path: expected {app}/{environment}']], 404);
        }

        [$appSlug, $envName] = $segments;

        $app = App::where('slug', $appSlug)->first();

        if (!$app) {
            return response()->json(['errors' => ["no app found with slug \"{$appSlug}\""]], 404);
        }

        // Check user can view this app
        if ($request->user()->cannot('view', $app)) {
            return response()->json(['errors' => ['permission denied']], 403);
        }

        // Find the environment by type name (case-insensitive)
        $environment = $app->environments()
            ->whereHas('environmentType', fn ($q) => $q->whereRaw('LOWER(name) = ?', [strtolower($envName)]))
            ->first();

        if (!$environment) {
            return response()->json(['errors' => ["no environment \"{$envName}\" found on app \"{$appSlug}\""]], 404);
        }

        // Build the key-value map from variables
        $data = [];
        $latestTime = null;
        $maxVersion = 0;

        foreach ($environment->variables as $variable) {
            $latest = $variable->latest_version;
            if ($latest) {
                $data[$variable->key] = $latest->value;
                if (!$latestTime || $latest->created_at->gt($latestTime)) {
                    $latestTime = $latest->created_at;
                }
                $versionCount = $variable->versions()->count();
                if ($versionCount > $maxVersion) {
                    $maxVersion = $versionCount;
                }
            } else {
                $data[$variable->key] = '';
            }
        }

        return response()->json([
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'lease_id' => '',
            'renewable' => false,
            'lease_duration' => 0,
            'data' => [
                'data' => $data,
                'metadata' => [
                    'created_time' => $latestTime?->toIso8601String() ?? now()->toIso8601String(),
                    'custom_metadata' => [
                        'app' => $app->name,
                        'environment' => $environment->label,
                    ],
                    'deletion_time' => '',
                    'destroyed' => false,
                    'version' => $maxVersion,
                ],
            ],
        ]);
    }

    /**
     * GET/LIST /v1/secret/metadata/{path?}
     * List apps or environments in Vault metadata format.
     */
    public function listSecrets(Request $request, ?string $path = null): JsonResponse
    {
        $user = $request->user();

        // No path = list all apps the user can access
        if (empty($path)) {
            if ($user->isAdminOrOwner()) {
                $apps = App::pluck('slug');
            } else {
                $apps = $user->app_collaborations()->pluck('slug');
            }

            return response()->json([
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'data' => [
                    'keys' => $apps->map(fn ($slug) => $slug . '/')->values()->toArray(),
                ],
            ]);
        }

        // Path = app slug, list environments
        $appSlug = rtrim($path, '/');
        $app = App::where('slug', $appSlug)->first();

        if (!$app) {
            return response()->json(['errors' => ["no app found with slug \"{$appSlug}\""]], 404);
        }

        if ($user->cannot('view', $app)) {
            return response()->json(['errors' => ['permission denied']], 403);
        }

        $envNames = $app->environments()
            ->with('environmentType')
            ->get()
            ->map(fn ($env) => strtolower($env->environmentType->name ?? $env->label))
            ->unique()
            ->values()
            ->toArray();

        return response()->json([
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'data' => [
                'keys' => $envNames,
            ],
        ]);
    }
}
