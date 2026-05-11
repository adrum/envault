<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\LogEntry;
use App\Models\Variable;
use App\Support\EnvFile;
use Illuminate\Http\Request;
use App\Support\Webhooks\WebhookEvents;
use App\Support\Webhooks\WebhookDispatcher;

class VariableController extends Controller
{
    public function store(Request $request, App $app)
    {
        $this->authorize('createVariable', $app);

        $validated = $request->validate([
            'key' => ['required', 'string', 'alpha_dash', 'max:255'],
            'value' => ['nullable', 'string'],
            'environment_id' => ['nullable', 'exists:environments,id'],
        ]);

        $validated['environment_id'] = $validated['environment_id']
            ?? $app->environments()->value('environments.id');

        // Check key uniqueness within environment
        $environment = \App\Models\Environment::findOrFail($validated['environment_id']);
        if ($environment->variables()->where('key', $validated['key'])->exists()) {
            return back()->withErrors(['key' => 'This variable key already exists in this environment.']);
        }

        $maxOrder = $app->variables()->max('sort_order') ?? -1;
        /** @var Variable $variable */
        $variable = $app->variables()->create([
            'key' => $validated['key'],
            'environment_id' => $validated['environment_id'],
            'sort_order' => $maxOrder + 1,
        ]);
        $variable->versions()->create(['value' => $validated['value'] ?? '', 'user_id' => $request->user()->id]);

        LogEntry::create([
            'action' => 'created',
            'description' => "Created variable \"{$validated['key']}\" in \"{$app->name}\"",
            'loggable_type' => 'variable',
            'loggable_id' => $variable->id,
            'user_id' => $request->user()->id,
        ]);

        if ($app->notificationsEnabled()) {
            $app->notify(new \App\Notifications\VariableCreatedNotification($variable, $request->user()));
        }

        app(WebhookDispatcher::class)->dispatch(
            WebhookEvents::VARIABLE_CREATED,
            $app,
            $environment,
            $request->user(),
            ['variable' => $variable],
        );

        toastSuccess("Variable \"{$variable->key}\" created.");

        return back();
    }

    public function import(Request $request, App $app)
    {
        $this->authorize('createVariable', $app);

        $validated = $request->validate([
            'env_content' => ['required', 'string'],
            'environment_id' => ['nullable', 'exists:environments,id'],
        ]);

        $validated['environment_id'] = $validated['environment_id']
            ?? $app->environments()->value('environments.id');

        $entries = EnvFile::parse($validated['env_content']);
        $imported = 0;
        $sortOrder = 0;
        $processedKeys = [];
        $originalOrder = $app->variables()
            ->where('environment_id', $validated['environment_id'])
            ->orderBy('sort_order')
            ->pluck('key')
            ->all();

        foreach ($entries as $entry) {
            $key = $entry['key'];
            $value = $entry['value'];

            /** @var Variable $variable */
            $variable = $app->variables()->where('environment_id', $validated['environment_id'])->firstOrCreate(
                ['key' => $key],
                ['environment_id' => $validated['environment_id']],
            );
            $variable->update(['sort_order' => $sortOrder]);

            // Only create a new version if the value actually changed
            $currentValue = $variable->latest_version?->value;
            if ($currentValue !== $value) {
                $variable->versions()->create(['value' => $value, 'user_id' => $request->user()->id]);
                $imported++;
            }

            $processedKeys[] = $key;
            $sortOrder++;
        }

        // Any existing variables not in the import are removed
        $deleted = 0;
        $app->variables()
            ->where('environment_id', $validated['environment_id'])
            ->whereNotIn('key', $processedKeys)
            ->each(function ($variable) use ($app, $request, &$deleted, &$originalOrder) {
                $originalOrder = array_values(array_filter($originalOrder, fn ($k) => $k !== $variable->key));
                LogEntry::create([
                    'action' => 'deleted',
                    'description' => "Deleted variable \"{$variable->key}\" from \"{$app->name}\"",
                    'loggable_type' => 'variable',
                    'loggable_id' => $variable->id,
                    'user_id' => $request->user()->id,
                ]);
                $variable->delete();
                $deleted++;
            });

        $reordered = array_values(array_intersect($processedKeys, $originalOrder)) !== array_values($originalOrder);

        if ($imported > 0) {
            LogEntry::create([
                'action' => 'imported',
                'description' => "Imported {$imported} variables into \"{$app->name}\"",
                'loggable_type' => 'app',
                'loggable_id' => $app->id,
                'user_id' => $request->user()->id,
            ]);

            if ($app->notificationsEnabled()) {
                $app->notify(new \App\Notifications\VariablesImportedNotification($imported, $request->user()));
            }
        } elseif ($reordered && $deleted === 0) {
            LogEntry::create([
                'action' => 'reordered',
                'description' => "Reordered variables in \"{$app->name}\"",
                'loggable_type' => 'app',
                'loggable_id' => $app->id,
                'user_id' => $request->user()->id,
            ]);
        }

        if ($imported > 0 || $deleted > 0) {
            app(WebhookDispatcher::class)->dispatch(
                WebhookEvents::VARIABLES_IMPORTED,
                $app,
                \App\Models\Environment::find($validated['environment_id']),
                $request->user(),
                ['imported' => $imported, 'deleted' => $deleted],
            );

            $parts = [];
            if ($imported > 0) {
                $parts[] = $imported . ' imported';
            }
            if ($deleted > 0) {
                $parts[] = $deleted . ' removed';
            }
            toastSuccess('Variables synced (' . implode(', ', $parts) . ').');
        } elseif ($reordered) {
            toastSuccess('Variables reordered.');
        } else {
            toastInfo('No changes detected.');
        }

        return back();
    }

    public function update(Request $request, Variable $variable)
    {
        $this->authorize('update', $variable);

        $validated = $request->validate([
            'key' => ['sometimes', 'required', 'string', 'alpha_dash', 'max:255'],
            'value' => ['sometimes', 'nullable', 'string'],
        ]);

        $app = $variable->app;

        if (isset($validated['key']) && $validated['key'] !== $variable->key) {
            if ($app->variables()->where('environment_id', $variable->environment_id)->where('key', $validated['key'])->where('id', '!=', $variable->id)->exists()) {
                return back()->withErrors(['key' => 'This variable key already exists in this environment.']);
            }

            $oldKey = $variable->key;
            $variable->update(['key' => $validated['key']]);

            LogEntry::create([
                'action' => 'updated_key',
                'description' => "Updated variable key from \"{$oldKey}\" to \"{$validated['key']}\" in \"{$app->name}\"",
                'loggable_type' => 'variable',
                'loggable_id' => $variable->id,
                'user_id' => $request->user()->id,
            ]);

            if ($app->notificationsEnabled()) {
                $app->notify(new \App\Notifications\VariableUpdatedNotification($variable, $request->user(), 'key'));
            }
        }

        if (isset($validated['value'])) {
            $variable->versions()->create(['value' => $validated['value'], 'user_id' => $request->user()->id]);

            LogEntry::create([
                'action' => 'updated_value',
                'description' => "Updated value of \"{$variable->key}\" in \"{$app->name}\"",
                'loggable_type' => 'variable',
                'loggable_id' => $variable->id,
                'user_id' => $request->user()->id,
            ]);

            if ($app->notificationsEnabled()) {
                $app->notify(new \App\Notifications\VariableUpdatedNotification($variable, $request->user(), 'value'));
            }
        }

        app(WebhookDispatcher::class)->dispatch(
            WebhookEvents::VARIABLE_UPDATED,
            $app,
            $variable->environment,
            $request->user(),
            ['variable' => $variable],
        );

        toastSuccess("Variable \"{$variable->key}\" updated.");

        return back();
    }

    public function destroy(Variable $variable)
    {
        $this->authorize('delete', $variable);

        $app = $variable->app;

        LogEntry::create([
            'action' => 'deleted',
            'description' => "Deleted variable \"{$variable->key}\" from \"{$app->name}\"",
            'loggable_type' => 'variable',
            'loggable_id' => $variable->id,
            'user_id' => request()->user()->id,
        ]);

        $environment = $variable->environment;
        $variable->delete();

        app(WebhookDispatcher::class)->dispatch(
            WebhookEvents::VARIABLE_DELETED,
            $app,
            $environment,
            request()->user(),
            ['variable' => $variable],
        );

        toastSuccess("Variable \"{$variable->key}\" deleted.");

        return back();
    }

    public function rollback(Request $request, Variable $variable)
    {
        $this->authorize('update', $variable);

        $validated = $request->validate([
            'version_id' => ['required', 'exists:variable_versions,id'],
        ]);

        /** @var \App\Models\VariableVersion $version */
        $version = $variable->versions()->findOrFail($validated['version_id']);
        $variable->versions()->create(['value' => $version->value, 'user_id' => $request->user()->id]);

        $app = $variable->app;

        LogEntry::create([
            'action' => 'rolled_back',
            'description' => "Rolled back \"{$variable->key}\" in \"{$app->name}\" to a previous version",
            'loggable_type' => 'variable',
            'loggable_id' => $variable->id,
            'user_id' => $request->user()->id,
        ]);

        app(WebhookDispatcher::class)->dispatch(
            WebhookEvents::VARIABLE_ROLLED_BACK,
            $app,
            $variable->environment,
            $request->user(),
            ['variable' => $variable],
        );

        toastSuccess("Rolled back \"{$variable->key}\" to a previous version.");

        return back();
    }

    public function export(App $app)
    {
        $this->authorize('view', $app);

        $environmentId = request()->get('environment');
        if ($environmentId) {
            $variables = $app->variables()->where('environment_id', $environmentId)->with(['versions' => fn ($q) => $q->latest()->limit(1)])->get();
        } else {
            $app->load(['variables.versions' => fn ($q) => $q->latest()->limit(1)]);
            $variables = $app->variables;
        }

        $env = EnvFile::serialize($variables->map(function (Variable $variable) {
            return [
                'key' => $variable->key,
                'value' => $variable->latest_version !== null ? $variable->latest_version->value : '',
            ];
        })->all());

        return response($env, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$app->name}.env\"",
        ]);
    }
}
