<?php

namespace App\Http\Controllers;

use App\Models\App;
use Inertia\Inertia;
use App\Models\LogEntry;
use Illuminate\Http\Request;

class AppController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $search = $request->get('search', '');

        if ($user->isAdminOrOwner()) {
            $query = App::query();
        } else {
            $query = $user->app_collaborations();
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $apps = $query->withCount('variables')->paginate(10)->withQueryString();

        return Inertia::render('apps/index', [
            'apps' => $apps,
            'search' => $search,
        ]);
    }

    public function show(App $app)
    {
        $this->authorize('view', $app);

        $app->load(['variables.versions' => fn ($q) => $q->with('user:id,first_name,last_name')->latest()]);

        $setupToken = null;
        $plainToken = null;
        if (request()->user()->isAdminOrOwner() || request()->user()->isAppAdmin($app)) {
            $plainToken = \Illuminate\Support\Str::random(16);
            $setupToken = $app->setup_tokens()->create([
                'token' => $plainToken,
                'user_id' => request()->user()->id,
            ]);
        }

        return Inertia::render('apps/show', [
            'app' => $app,
            'setupToken' => $setupToken ? ['app_id' => $app->id, 'plain_token' => $plainToken] : null,
            'canManage' => request()->user()->can('update', $app),
            'canCreateVariable' => request()->user()->can('createVariable', $app),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', App::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $app = App::create($validated);

        LogEntry::create([
            'action' => 'created',
            'description' => "Created app \"{$app->name}\"",
            'loggable_type' => $app->getMorphClass(),
            'loggable_id' => $app->id,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('apps.show', $app);
    }

    public function edit(App $app)
    {
        $this->authorize('update', $app);

        $app->load('collaborators');

        // Admin/owner users always appear in the collaborators list
        $adminUsers = \App\Models\User::whereIn('role', ['admin', 'owner'])->get();

        // Users available to add as collaborators (non-admin, not already a collaborator)
        $existingIds = $app->collaborators->pluck('id')->merge($adminUsers->pluck('id'))->unique();
        $availableUsers = \App\Models\User::whereNotIn('id', $existingIds)->get();

        return Inertia::render('apps/edit', [
            'app' => $app,
            'adminUsers' => $adminUsers,
            'availableUsers' => $availableUsers,
        ]);
    }

    public function update(Request $request, App $app)
    {
        $this->authorize('update', $app);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $oldName = $app->name;
        $app->update($validated);

        if ($oldName !== $app->name) {
            LogEntry::create([
                'action' => 'updated_name',
                'description' => "Updated app name from \"{$oldName}\" to \"{$app->name}\"",
                'loggable_type' => 'app',
                'loggable_id' => $app->id,
                'user_id' => $request->user()->id,
            ]);
        }

        return back();
    }

    public function destroy(Request $request, App $app)
    {
        $this->authorize('delete', $app);

        $request->validate([
            'confirm_name' => ['required', 'string'],
        ]);

        if ($request->input('confirm_name') !== $app->name) {
            return back()->withErrors(['confirm_name' => 'The app name does not match.']);
        }

        LogEntry::create([
            'action' => 'deleted',
            'description' => "Deleted app \"{$app->name}\"",
            'loggable_type' => 'app',
            'loggable_id' => $app->id,
            'user_id' => $request->user()->id,
        ]);

        $app->delete();

        return redirect()->route('apps.index');
    }

    public function updateNotifications(Request $request, App $app)
    {
        $this->authorize('update', $app);

        $validated = $request->validate([
            'slack_notification_channel' => ['nullable', 'string', 'max:255'],
            'slack_notification_webhook_url' => ['nullable', 'url', 'max:255'],
        ]);

        $app->update($validated);

        LogEntry::create([
            'action' => 'updated_notifications',
            'description' => "Updated notification settings for \"{$app->name}\"",
            'loggable_type' => 'app',
            'loggable_id' => $app->id,
            'user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function addCollaborator(Request $request, App $app)
    {
        $this->authorize('update', $app);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['nullable', 'in:admin,collaborator'],
        ]);

        $app->collaborators()->attach($validated['user_id'], [
            'role' => $validated['role'] ?? 'collaborator',
        ]);

        $user = \App\Models\User::find($validated['user_id']);

        LogEntry::create([
            'action' => 'added_collaborator',
            'description' => "Added {$user->name} as a collaborator to \"{$app->name}\"",
            'loggable_type' => 'app',
            'loggable_id' => $app->id,
            'user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function removeCollaborator(Request $request, App $app)
    {
        $this->authorize('update', $app);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = \App\Models\User::find($validated['user_id']);

        $app->collaborators()->detach($validated['user_id']);

        LogEntry::create([
            'action' => 'removed_collaborator',
            'description' => "Removed {$user->name} from \"{$app->name}\"",
            'loggable_type' => 'app',
            'loggable_id' => $app->id,
            'user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function updateCollaboratorRole(Request $request, App $app)
    {
        $this->authorize('update', $app);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:admin,collaborator'],
        ]);

        $app->collaborators()->updateExistingPivot($validated['user_id'], [
            'role' => $validated['role'],
        ]);

        $user = \App\Models\User::find($validated['user_id']);

        LogEntry::create([
            'action' => 'updated_collaborator_role',
            'description' => "Updated {$user->name}'s role to {$validated['role']} on \"{$app->name}\"",
            'loggable_type' => 'app',
            'loggable_id' => $app->id,
            'user_id' => $request->user()->id,
        ]);

        return back();
    }
}
