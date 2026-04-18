<?php

namespace App\Http\Controllers\Api;

use App\Models\App;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class UpdateAppController extends Controller
{
    public function __invoke(App $app): JsonResponse
    {
        $this->authorize('view', $app);

        $app->load('variables');

        return response()->json($app);
    }
}
