<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Variable;

class VariablePolicy
{
    public function view(User $user, Variable $variable): bool
    {
        if ($user->isAdminOrOwner()) {
            return true;
        }

        $app = $variable->app;

        return $app !== null && $user->isAppCollaborator($app);
    }

    public function update(User $user, Variable $variable): bool
    {
        if ($user->isAdminOrOwner()) {
            return true;
        }

        $app = $variable->app;

        return $app !== null && $user->isAppAdmin($app);
    }

    public function delete(User $user, Variable $variable): bool
    {
        if ($user->isAdminOrOwner()) {
            return true;
        }

        $app = $variable->app;

        return $app !== null && $user->isAppAdmin($app);
    }
}
