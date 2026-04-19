<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\LogEntry;
use App\Models\Environment;
use Illuminate\Http\Request;
use App\Models\EnvironmentType;

class EnvironmentController extends Controller
{
    public function store(Request $request, App $app)
    {
        $this->authorize('update', $app);

        $validated = $request->validate([
            'environment_type_id' => ['required', 'exists:environment_types,id'],
            'custom_label' => ['nullable', 'string', 'max:255'],
        ]);

        $type = EnvironmentType::findOrFail($validated['environment_type_id']);

        if ($type->per_app_limit !== null) {
            $currentCount = $app->environments()->where('environments.environment_type_id', $type->id)->count();
            if ($currentCount >= $type->per_app_limit) {
                return back()->withErrors(['environment_type_id' => "This app already has the maximum number of \"{$type->name}\" environments."]);
            }
        }

        $customLabel = null;
        if ($type->per_app_limit !== 1 && !empty($validated['custom_label'])) {
            $customLabel = $validated['custom_label'];
        }

        $app->environments()->create([
            'environment_type_id' => $type->id,
            'label' => $type->name,
            'color' => $type->color,
            'custom_label' => $customLabel,
        ]);

        LogEntry::create([
            'action' => 'environment_added',
            'description' => 'Added "' . ($customLabel ?? $type->name) . "\" environment to \"{$app->name}\"",
            'loggable_type' => 'app',
            'loggable_id' => $app->id,
            'user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function update(Request $request, Environment $environment)
    {
        $this->authorize('update', $environment->app);

        $validated = $request->validate([
            'environment_type_id' => ['sometimes', 'exists:environment_types,id'],
            'custom_label' => ['nullable', 'string', 'max:255'],
        ]);

        $app = $environment->app;
        $oldLabel = $environment->label;

        // Reclassify to a different type
        if (isset($validated['environment_type_id']) && $validated['environment_type_id'] != $environment->environment_type_id) {
            $newType = EnvironmentType::findOrFail($validated['environment_type_id']);

            if ($newType->per_app_limit !== null) {
                $currentCount = $app->environments()->where('environment_type_id', $newType->id)->count();
                if ($currentCount >= $newType->per_app_limit) {
                    return back()->withErrors(['environment_type_id' => "This app already has the maximum number of \"{$newType->name}\" environments."]);
                }
            }

            $environment->update([
                'environment_type_id' => $newType->id,
                'label' => $newType->name,
                'color' => $newType->color,
                'custom_label' => $newType->per_app_limit === 1 ? null : ($validated['custom_label'] ?? $environment->custom_label),
            ]);

            LogEntry::create([
                'action' => 'environment_reclassified',
                'description' => "Reclassified \"{$oldLabel}\" to \"{$environment->label}\" on \"{$app->name}\"",
                'loggable_type' => 'app',
                'loggable_id' => $app->id,
                'user_id' => $request->user()->id,
            ]);

            return back();
        }

        // Custom label update only
        $type = $environment->environmentType;
        if ($type && $type->per_app_limit === 1) {
            $environment->update(['custom_label' => null]);
        } else {
            $environment->update(['custom_label' => $validated['custom_label'] ?? null]);
        }

        if ($oldLabel !== $environment->label) {
            LogEntry::create([
                'action' => 'environment_renamed',
                'description' => "Renamed environment from \"{$oldLabel}\" to \"{$environment->label}\" on \"{$app->name}\"",
                'loggable_type' => 'app',
                'loggable_id' => $app->id,
                'user_id' => $request->user()->id,
            ]);
        }

        return back();
    }

    public function destroy(Request $request, Environment $environment)
    {
        $app = $environment->app;
        $this->authorize('update', $app);

        if ($app->environments()->count() <= 1) {
            return back()->withErrors(['environment' => 'Cannot delete the last environment.']);
        }

        $request->validate([
            'confirm_label' => ['required', 'string'],
        ]);

        if ($request->input('confirm_label') !== $environment->label) {
            return back()->withErrors(['confirm_label' => 'The label does not match.']);
        }

        $label = $environment->label;

        LogEntry::create([
            'action' => 'environment_deleted',
            'description' => "Deleted \"{$label}\" environment from \"{$app->name}\"",
            'loggable_type' => 'app',
            'loggable_id' => $app->id,
            'user_id' => $request->user()->id,
        ]);

        $environment->variables()->delete();
        $environment->delete();

        return back();
    }
}
