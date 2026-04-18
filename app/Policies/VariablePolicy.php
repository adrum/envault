<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Variable;

class VariablePolicy
{
    /**
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, Variable $variable)
    {
        return $user->isAdminOrOwner() || $user->isAppAdmin($variable->app);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, Variable $variable)
    {
        return $user->isAdminOrOwner() || $user->isAppAdmin($variable->app);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, Variable $variable)
    {
        return $user->isAdminOrOwner() || $user->isAppCollaborator($variable->app);
    }
}
