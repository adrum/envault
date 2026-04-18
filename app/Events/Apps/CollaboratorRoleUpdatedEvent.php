<?php

namespace App\Events\Apps;

use App\Models\App;
use App\Models\User;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class CollaboratorRoleUpdatedEvent
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
     * @var string
     */
    public $newRole;

    /**
     * @var string
     */
    public $oldRole;

    /**
     * Create a new event instance.
     *
     * @param  string  $oldRole
     * @param  string  $newRole
     * @return void
     */
    public function __construct(App $app, User $collaborator, $oldRole, $newRole)
    {
        $this->app = $app;
        $this->collaborator = $collaborator;
        $this->newRole = $newRole;
        $this->oldRole = $oldRole;
    }
}
