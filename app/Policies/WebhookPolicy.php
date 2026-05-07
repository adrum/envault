<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;

class WebhookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminOrOwner();
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $user->isAdminOrOwner();
    }

    public function create(User $user): bool
    {
        return $user->isAdminOrOwner();
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $user->isAdminOrOwner();
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $user->isAdminOrOwner();
    }
}
