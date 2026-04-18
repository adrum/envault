<?php

namespace App\Observers;

use App\Models\App;

class AppObserver
{
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
