<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\LogEntry;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = User::all();

        return Inertia::render('users/index', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = User::create($validated);

        LogEntry::create([
            'action' => 'created',
            'description' => "Created user \"{$user->name}\"",
            'loggable_type' => 'user',
            'loggable_id' => $user->id,
            'user_id' => $request->user()->id,
        ]);

        toastSuccess("User \"{$user->name}\" created.");

        return back();
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['sometimes', 'string', 'in:user,admin,owner'],
        ]);

        // Handle role change separately (requires additional authorization)
        $newRole = null;
        if (isset($validated['role']) && $validated['role'] !== $user->getRawOriginal('role')) {
            $this->authorize('updateRole', $user);
            $newRole = $validated['role'];
        }

        // Update profile fields (excluding role)
        $profileData = collect($validated)->except('role')->all();
        $hasProfileChanges = $profileData['first_name'] !== $user->first_name
            || $profileData['last_name'] !== $user->last_name
            || $profileData['email'] !== $user->email;

        $user->update($profileData);

        if ($hasProfileChanges) {
            LogEntry::create([
                'action' => 'updated',
                'description' => "Updated user \"{$user->name}\"",
                'loggable_type' => 'user',
                'loggable_id' => $user->id,
                'user_id' => $request->user()->id,
            ]);
        }

        // Apply role change
        if ($newRole) {
            $user->update(['role' => $newRole]);

            LogEntry::create([
                'action' => 'updated_role',
                'description' => "Updated {$user->name}'s role to {$newRole}",
                'loggable_type' => 'user',
                'loggable_id' => $user->id,
                'user_id' => $request->user()->id,
            ]);
        }

        if ($hasProfileChanges || $newRole) {
            toastSuccess("User \"{$user->name}\" updated.");
        }

        return back();
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        LogEntry::create([
            'action' => 'deleted',
            'description' => "Deleted user \"{$user->name}\"",
            'loggable_type' => 'user',
            'loggable_id' => $user->id,
            'user_id' => $request->user()->id,
        ]);

        $user->delete();

        toastSuccess("User \"{$user->name}\" deleted.");

        return back();
    }
}
