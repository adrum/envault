<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdminOrOwner();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdminOrOwner();
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdminOrOwner();
    }

    public function update(User $user, User $model): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return $user->isAdminOrOwner() && !$model->isAdminOrOwner();
    }

    public function updateRole(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return $user->isOwner();
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        return $user->isAdminOrOwner() && !$model->isAdminOrOwner();
    }
}
