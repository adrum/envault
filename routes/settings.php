<?php

use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    if (Features::enabled(Features::updatePasswords())) {
        Route::put('settings/password', [SecurityController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('user-password.update');
    }

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/tokens', [App\Http\Controllers\Settings\ApiTokenController::class, 'index'])->name('tokens.index');
    Route::post('settings/tokens', [App\Http\Controllers\Settings\ApiTokenController::class, 'store'])->name('tokens.store');
    Route::patch('settings/tokens/{token}', [App\Http\Controllers\Settings\ApiTokenController::class, 'update'])->name('tokens.update');
    Route::delete('settings/tokens/{token}', [App\Http\Controllers\Settings\ApiTokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('settings/environments', [App\Http\Controllers\Settings\EnvironmentTypeController::class, 'index'])->name('environment-types.index');
    Route::post('settings/environments', [App\Http\Controllers\Settings\EnvironmentTypeController::class, 'store'])->name('environment-types.store');
    Route::patch('settings/environments/{environmentType}', [App\Http\Controllers\Settings\EnvironmentTypeController::class, 'update'])->name('environment-types.update');
    Route::post('settings/environments/reorder', [App\Http\Controllers\Settings\EnvironmentTypeController::class, 'reorder'])->name('environment-types.reorder');
    Route::delete('settings/environments/{environmentType}', [App\Http\Controllers\Settings\EnvironmentTypeController::class, 'destroy'])->name('environment-types.destroy');
});
