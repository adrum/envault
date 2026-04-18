<?php

namespace App\Observers;

use App\Models\Variable;

class VariableObserver
{
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
