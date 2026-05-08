<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Support\Warnings\WarningEvaluator;

class EnvironmentWarningController extends Controller
{
    /**
     * Evaluate warnings against a proposed key/value state for the given
     * environment without persisting any changes. Returns a list of
     * warnings the user must acknowledge before saving.
     */
    public function preflight(Request $request, App $app, Environment $environment, WarningEvaluator $evaluator): JsonResponse
    {
        $this->authorize('createVariable', $app);

        abort_unless($environment->app_id === $app->id, 404);

        $validated = $request->validate([
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string'],
        ]);

        $values = array_map(fn ($v) => (string) ($v ?? ''), $validated['values']);

        $warnings = array_map(
            fn ($warning) => $warning->toArray(),
            $evaluator->evaluate($environment, $values),
        );

        return response()->json([
            'warnings' => $warnings,
            'detected_frameworks' => $evaluator->detectFrameworks($values),
        ]);
    }
}
