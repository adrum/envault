<?php

use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;
use App\Http\Controllers\LogController;
use App\Http\Middleware\RedirectIfSetup;
use App\Http\Controllers\SetupController;
use App\Http\Middleware\RedirectIfNotSetup;
use App\Http\Controllers\VariableController;
use App\Http\Controllers\UserManagementController;

// Setup (first-run)
Route::middleware(RedirectIfSetup::class)->group(function () {
    Route::get('setup', [SetupController::class, 'create'])->name('setup');
    Route::post('setup', [SetupController::class, 'store']);
});

// Email code auth (passwordless)
Route::middleware('guest')->group(function () {
    // Email Logins
    Route::get('auth/email-code', [App\Http\Controllers\Auth\EmailCodeController::class, 'create'])->name('auth.email-code');
    Route::post('auth/email-code/request', [App\Http\Controllers\Auth\EmailCodeController::class, 'requestCode'])->name('auth.email-code.request');
    Route::post('auth/email-code/verify', [App\Http\Controllers\Auth\EmailCodeController::class, 'verifyCode'])->name('auth.email-code.verify');

    // Password login (secondary)
    Route::get('login/password', fn () => Features::canUpdatePasswords() ? inertia('auth/login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'canRegister' => Features::enabled(Features::registration()),
        'status' => request()->session()->get('status'),
    ]) : redirect()->route('login'))->name('login.password');
});

Route::middleware(RedirectIfNotSetup::class)->group(function () {
    Route::redirect('/', '/apps')->name('home');

    Route::middleware(['auth', 'verified'])->group(function () {
        // Redirect dashboard to apps
        Route::redirect('dashboard', '/apps')->name('dashboard');

        // Apps
        Route::get('apps', [AppController::class, 'index'])->name('apps.index');
        Route::post('apps', [AppController::class, 'store'])->name('apps.store');
        Route::get('apps/{app}', [AppController::class, 'show'])->name('apps.show');
        Route::get('apps/{app}/edit', [AppController::class, 'edit'])->name('apps.edit');
        Route::patch('apps/{app}', [AppController::class, 'update'])->name('apps.update');
        Route::delete('apps/{app}', [AppController::class, 'destroy'])->name('apps.destroy');
        Route::patch('apps/{app}/notifications', [AppController::class, 'updateNotifications'])->name('apps.notifications.update');
        Route::post('apps/{app}/collaborators', [AppController::class, 'addCollaborator'])->name('apps.collaborators.add');
        Route::delete('apps/{app}/collaborators', [AppController::class, 'removeCollaborator'])->name('apps.collaborators.remove');
        Route::patch('apps/{app}/collaborators', [AppController::class, 'updateCollaboratorRole'])->name('apps.collaborators.update-role');

        // Variables
        Route::post('apps/{app}/variables', [VariableController::class, 'store'])->name('variables.store');
        Route::post('apps/{app}/variables/import', [VariableController::class, 'import'])->name('variables.import');
        Route::get('apps/{app}/variables/export', [VariableController::class, 'export'])->name('variables.export');
        Route::patch('variables/{variable}', [VariableController::class, 'update'])->name('variables.update');
        Route::delete('variables/{variable}', [VariableController::class, 'destroy'])->name('variables.destroy');
        Route::post('variables/{variable}/rollback', [VariableController::class, 'rollback'])->name('variables.rollback');

        // Users (admin)
        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        // Impersonation
        Route::post('impersonate/{user}', [App\Http\Controllers\ImpersonateController::class, 'start'])->name('impersonate.start');
        Route::post('impersonate-stop', [App\Http\Controllers\ImpersonateController::class, 'stop'])->name('impersonate.stop');

        // Audit Log
        Route::get('log', LogController::class)->name('log');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');

        Route::post('session/extend', fn () => ['status' => 'ok'])->name('session.extend');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/sso.php';
