<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Enums\UserRole;
use App\Models\LogEntry;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\Auth;

class SetupController extends Controller
{
    public function create()
    {
        return Inertia::render('setup');
    }

    public function store(Request $request)
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ];

        if (Features::enabled(Features::updatePasswords())) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        $validated = $request->validate($rules);

        $user = User::create([
            ...$validated,
            'role' => UserRole::OWNER,
            'email_verified_at' => now(),
        ]);

        LogEntry::create([
            'action' => 'created',
            'description' => "Created owner account \"{$user->name}\"",
            'loggable_type' => 'user',
            'loggable_id' => $user->id,
            'user_id' => $user->id,
        ]);

        Auth::login($user);

        return redirect()->route('apps.index');
    }
}
