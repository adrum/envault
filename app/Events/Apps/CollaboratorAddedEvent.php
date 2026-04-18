<?php

namespace App\Events\Apps;

use App\Models\App;
use App\Models\User;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class CollaboratorAddedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var App
     */
    public $app;

    /**
     * @var User
     */
    public $collaborator;

    /**
     * Create a new event instance.
     *
     * @param  App  $app
     * @param  User  $collaborator
     * @return void
     */
    public function __construct(App $app, User $collaborator)
    {
        $this->app = $app;
        $this->collaborator = $collaborator;
    }
}
