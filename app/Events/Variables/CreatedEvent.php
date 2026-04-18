<?php

namespace App\Events\Variables;

use App\Models\App;
use App\Models\User;
use App\Models\Variable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class CreatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public App $app,
        public Variable $variable,
        public User $user,
    ) {}
}
