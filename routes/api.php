<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SetupAppController;
use App\Http\Controllers\Api\UpdateAppController;

Route::get('version', fn () => response()->json(['version' => config('app.version', '2.0.0')]));

Route::prefix('v1')->group(function () {
    Route::post('apps/{app}/setup/{token}', SetupAppController::class);
    Route::post('apps/{app}/update', UpdateAppController::class)->middleware('auth:sanctum');
});
