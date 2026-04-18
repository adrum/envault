<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
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
     * Determine whether the user can delete the model.
     *
     * @return mixed
     */
    public function delete(User $user, User $model)
    {
        return $user->id != $model->id && ($user->isOwner() || ($user->isAdminOrOwner() && !$model->isAdminOrOwner()));
    }

    /**
     * Determine whether the user can update the model.
     *
     * @return mixed
     */
    public function update(User $user, User $model)
    {
        return $user->isOwner() || ($user->isAdminOrOwner() && !$model->isAdminOrOwner());
    }

    /**
     * Determine whether the user can update the model's role.
     *
     * @return mixed
     */
    public function updateRole(User $user, User $model)
    {
        return $user->id != $model->id && $user->isOwner();
    }

    /**
     * Determine whether the user can view the model.
     *
     * @return mixed
     */
    public function view(User $user, User $model)
    {
        return $user->isAdminOrOwner();
    }

    /**
     * Determine whether the user can view any models.
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        return $user->isAdminOrOwner();
    }
}
