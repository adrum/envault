<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VaultTokenAuth;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\SetupAppController;
use App\Http\Controllers\Api\UpdateAppController;

Route::get('version', fn () => response()->json(['version' => config('app.version', '2.0.0')]));

Route::prefix('v1')->group(function () {
    // Legacy Envault CLI endpoints
    Route::post('apps/{app}/setup/{token}', SetupAppController::class);
    Route::post('apps/{app}/update', UpdateAppController::class)->middleware('auth:sanctum');

    // Vault-compatible KV v2 API
    Route::get('sys/health', [VaultController::class, 'health']);

    Route::middleware(VaultTokenAuth::class)->group(function () {
        Route::get('auth/token/lookup-self', [VaultController::class, 'tokenLookup']);
        Route::get('secret/data/{path}', [VaultController::class, 'readSecret'])->where('path', '.*');
        Route::get('secret/metadata/{path?}', [VaultController::class, 'listSecrets'])->where('path', '.*');
    });
});
