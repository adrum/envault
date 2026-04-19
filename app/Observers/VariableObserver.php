<?php

namespace App\Observers;

use App\Models\Variable;
use App\Models\Environment;

class VariableObserver
{
    public function creating(Variable $variable): void
    {
        if (empty($variable->environment_id) && !empty($variable->app_id)) {
            $defaultEnv = Environment::where('app_id', $variable->app_id)->orderBy('id')->first();
            if ($defaultEnv) {
                $variable->environment_id = $defaultEnv->id;
            }
        }
    }

    /**
     * Handle the variable "deleted" event.
     *
     * @return void
     */
    public function deleted(Variable $variable)
    {
        $variable->versions()->delete();
    }

    /**
     * Handle the variable "restored" event.
     *
     * @return void
     */
    public function restored(Variable $variable)
    {
        $variable->versions()->withTrashed()->where('deleted_at', '>=', $variable->deleted_at)->restore();
    }
}
