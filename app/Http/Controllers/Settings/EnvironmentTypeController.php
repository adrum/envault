<?php

namespace App\Http\Controllers\Settings;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\EnvironmentType;
use App\Http\Controllers\Controller;

class EnvironmentTypeController extends Controller
{
    public function index()
    {
        $this->authorize('administrate');

        $types = EnvironmentType::orderBy('sort_order')->get();

        return Inertia::render('settings/environment-types', [
            'types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('administrate');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:environment_types,name'],
            'color' => ['required', 'string', 'max:50'],
            'per_app_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $maxOrder = EnvironmentType::max('sort_order') ?? -1;

        EnvironmentType::create([
            ...$validated,
            'sort_order' => $maxOrder + 1,
        ]);

        toastSuccess("Environment type \"{$validated['name']}\" created.");

        return back();
    }

    public function update(Request $request, EnvironmentType $environmentType)
    {
        $this->authorize('administrate');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:environment_types,name,' . $environmentType->id],
            'color' => ['required', 'string', 'max:50'],
            'per_app_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $environmentType->update($validated);

        toastSuccess("Environment type \"{$environmentType->name}\" updated.");

        return back();
    }

    public function reorder(Request $request)
    {
        $this->authorize('administrate');

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'exists:environment_types,id'],
        ]);

        foreach ($validated['ids'] as $index => $id) {
            EnvironmentType::where('id', $id)->update(['sort_order' => $index]);
        }

        return back();
    }

    public function destroy(EnvironmentType $environmentType)
    {
        $this->authorize('administrate');

        $usageCount = $environmentType->environments()->count();
        if ($usageCount > 0) {
            $appNames = $environmentType->environments()
                ->with('app')
                ->get()
                ->pluck('app.name')
                ->unique()
                ->take(5)
                ->implode(', ');

            return back()->withErrors([
                'environment' => "This type is used by {$usageCount} environment(s) across apps: {$appNames}. Reclassify them first before deleting.",
            ]);
        }

        $name = $environmentType->name;
        $environmentType->delete();

        toastSuccess("Environment type \"{$name}\" deleted.");

        return back();
    }
}
