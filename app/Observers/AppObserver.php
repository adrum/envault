<?php

namespace App\Observers;

use App\Models\App;
use App\Models\Environment;
use App\Models\EnvironmentType;

class AppObserver
{
    public function created(App $app): void
    {
        $defaultType = EnvironmentType::orderBy('sort_order')->first()
            ?? EnvironmentType::create([
                'name' => 'Default',
                'color' => 'gray',
                'sort_order' => 0,
            ]);

        if (!Environment::where('app_id', $app->id)->exists()) {
            Environment::create([
                'app_id' => $app->id,
                'environment_type_id' => $defaultType->id,
                'label' => $defaultType->name,
                'color' => $defaultType->color,
            ]);
        }
    }

    /**
     * Handle the app "deleted" event.
     *
     * @return void
     */
    public function deleted(App $app)
    {
        $app->setup_tokens()->delete();

        $app->variables()->delete();
    }

    /**
     * Handle the app "restored" event.
     *
     * @return void
     */
    public function restored(App $app)
    {
        $app->variables()->withTrashed()->where('deleted_at', '>=', $app->deleted_at)->restore();
    }

    /**
     * Handle the app "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(App $app)
    {
        $app->log()->forceDelete();
    }
}
