<?php

namespace App\Events\Variables;

use App\Models\App;
use App\Models\User;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ImportedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public App $app,
        public int $count,
        public User $user,
    ) {}
}
