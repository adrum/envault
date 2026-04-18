<?php

namespace App\Policies;

use App\Models\App;
use App\Models\User;

class AppPolicy
{
    /**
     * Determine whether the user can create models.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        return $user->isAdminOrOwner();
    }

    /**
     * Determine whether the user can create variables for the model.
     *
     * @return mixed
     */
    public function createVariable(User $user, App $app)
    {
        return $user->isAdminOrOwner() || $user->isAppAdmin($app);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, App $app)
    {
        return $user->isAdminOrOwner();
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return mixed
     */
    public function forceDelete(User $user, App $app)
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return mixed
     */
    public function restore(User $user, App $app)
    {
        return $user->isAdminOrOwner();
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, App $app)
    {
        return $user->isAdminOrOwner();
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, App $app)
    {
        return $user->isAdminOrOwner() || $user->isAppCollaborator($app);
    }

    /**
     * Determine whether the user can view all models.
     *
     * @return mixed
     */
    public function viewAll(User $user)
    {
        return $user->isAdminOrOwner();
    }
}
