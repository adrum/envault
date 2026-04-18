<?php

namespace App\Events\Variables;

use App\Models\App;
use App\Models\User;
use App\Models\Variable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class KeyUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public App $app,
        public Variable $variable,
        public string $oldKey,
        public string $newKey,
        public User $user,
    ) {}
}
