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

        $environmentFilter = $request->get('environment_type');
        if ($environmentFilter) {
            $query->whereHas('environments', fn ($q) => $q->where('environment_type_id', $environmentFilter));
        }

        $apps = $query->with('environments')->withCount('variables')->paginate(10)->withQueryString();

        $environmentTypes = \App\Models\EnvironmentType::orderBy('sort_order')->get(['id', 'name', 'color']);

        return Inertia::render('apps/index', [
            'apps' => $apps,
            'search' => $search,
            'environment_type' => $environmentFilter,
            'environmentTypes' => $environmentTypes,
        ]);
    }

    public function show(App $app)
    {
        $this->authorize('view', $app);

        $app->load([
            'environments.variables.latest_version',
            'environments.variables.versions' => fn ($q) => $q->with('user:id,first_name,last_name')->latest(),
        ]);

        // Generate setup tokens per environment for admins
        $setupTokens = [];
        $canGenerateTokens = request()->user()->isAdminOrOwner() || request()->user()->isAppAdmin($app);

        if ($canGenerateTokens) {
            foreach ($app->environments as $env) {
                $plainToken = \Illuminate\Support\Str::random(16);
                $app->setup_tokens()->create([
                    'token' => $plainToken,
                    'environment_id' => $env->id,
                    'user_id' => request()->user()->id,
                ]);
                $setupTokens[$env->id] = $plainToken;
            }
        }

        return Inertia::render('apps/show', [
            'app' => $app,
            'setupTokens' => $setupTokens,
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

        // Create default environment
        $defaultType = \App\Models\EnvironmentType::query()
            ->orderBy('sort_order')
            ->first();

        if ($defaultType) {
            $app->environments()->create([
                'environment_type_id' => $defaultType->id,
                'label' => $defaultType->name,
                'color' => $defaultType->color,
            ]);
        }

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

        $app->load(['collaborators', 'environments']);
        $environmentTypes = \App\Models\EnvironmentType::orderBy('sort_order')->get();

        // Admin/owner users always appear in the collaborators list
        $adminUsers = \App\Models\User::whereIn('role', ['admin', 'owner'])->get();

        // Users available to add as collaborators (non-admin, not already a collaborator)
        $existingIds = $app->collaborators->pluck('id')->merge($adminUsers->pluck('id'))->unique();
        $availableUsers = \App\Models\User::whereNotIn('id', $existingIds)->get();

        return Inertia::render('apps/edit', [
            'app' => $app,
            'adminUsers' => $adminUsers,
            'availableUsers' => $availableUsers,
            'environmentTypes' => $environmentTypes,
        ]);
    }

    public function update(Request $request, App $app)
    {
        $this->authorize('update', $app);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'alpha_dash',
                'max:255',
                \Illuminate\Validation\Rule::unique('apps', 'slug')->ignore($app->id),
            ],
        ]);

        $oldName = $app->name;
        $oldSlug = $app->slug;

        if (array_key_exists('slug', $validated)) {
            $desired = trim((string) $validated['slug']);

            if ($desired === '') {
                $base = \Illuminate\Support\Str::slug($validated['name'] ?? $app->name) ?: 'app';
                $desired = $base;
                $counter = 2;
                while (App::withTrashed()->where('id', '!=', $app->id)->where('slug', $desired)->exists()) {
                    $desired = $base . '-' . $counter++;
                }
            }

            $validated['slug'] = $desired;
        }

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

        if (array_key_exists('slug', $validated) && $oldSlug !== $app->slug) {
            LogEntry::create([
                'action' => 'updated_slug',
                'description' => "Updated app slug from \"{$oldSlug}\" to \"{$app->slug}\"",
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
