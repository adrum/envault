<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the user "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(User $user)
    {
        $user->log()->forceDelete();
    }
}
