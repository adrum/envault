<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\LogEntry;
use App\Models\Variable;
use Illuminate\Http\Request;

class VariableController extends Controller
{
    public function store(Request $request, App $app)
    {
        $this->authorize('createVariable', $app);

        $validated = $request->validate([
            'key' => ['required', 'string', 'alpha_dash', 'max:255'],
            'value' => ['nullable', 'string'],
        ]);

        // Check key uniqueness within app
        if ($app->variables()->where('key', $validated['key'])->exists()) {
            return back()->withErrors(['key' => 'This variable key already exists in this app.']);
        }

        $maxOrder = $app->variables()->max('sort_order') ?? -1;
        /** @var Variable $variable */
        $variable = $app->variables()->create([
            'key' => $validated['key'],
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

        return back();
    }

    public function import(Request $request, App $app)
    {
        $this->authorize('createVariable', $app);

        $validated = $request->validate([
            'env_content' => ['required', 'string'],
        ]);

        $lines = explode("\n", $validated['env_content']);
        $imported = 0;
        $sortOrder = 0;
        $processedKeys = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Remove surrounding quotes from value
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            /** @var Variable $variable */
            $variable = $app->variables()->firstOrCreate(['key' => $key]);
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

        // Any existing variables not in the import keep their relative order after the imported ones
        $app->variables()
            ->whereNotIn('key', $processedKeys)
            ->orderBy('sort_order')
            ->each(function ($variable) use (&$sortOrder) {
                $variable->update(['sort_order' => $sortOrder++]);
            });

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
        } else {
            LogEntry::create([
                'action' => 'reordered',
                'description' => "Reordered variables in \"{$app->name}\"",
                'loggable_type' => 'app',
                'loggable_id' => $app->id,
                'user_id' => $request->user()->id,
            ]);
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
            if ($app->variables()->where('key', $validated['key'])->where('id', '!=', $variable->id)->exists()) {
                return back()->withErrors(['key' => 'This variable key already exists in this app.']);
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

        $variable->delete();

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

        return back();
    }

    public function export(App $app)
    {
        $this->authorize('view', $app);

        $app->load(['variables.versions' => fn ($q) => $q->latest()->limit(1)]);

        $env = $app->variables->map(function (Variable $variable) {
            $value = $variable->latest_version !== null ? $variable->latest_version->value : '';

            return "{$variable->key}={$value}";
        })->implode("\n");

        return response($env, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$app->name}.env\"",
        ]);
    }
}
