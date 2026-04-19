<?php

namespace App\Policies;

use App\Models\App;
use App\Models\User;

class AppPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdminOrOwner();
    }

    public function view(User $user, App $app): bool
    {
        return $user->isAdminOrOwner() || $user->isAppCollaborator($app);
    }

    public function viewAll(User $user): bool
    {
        return $user->isAdminOrOwner();
    }

    public function update(User $user, App $app): bool
    {
        return $user->isAdminOrOwner();
    }

    public function delete(User $user, App $app): bool
    {
        return $user->isAdminOrOwner();
    }

    public function forceDelete(User $user, App $app): bool
    {
        return $user->isOwner();
    }

    public function restore(User $user, App $app): bool
    {
        return $user->isAdminOrOwner();
    }

    public function createVariable(User $user, App $app): bool
    {
        return $user->isAdminOrOwner() || $user->isAppAdmin($app);
    }
}
