<?php

namespace App\Events\Users;

use App\Models\User;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class NameUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var string
     */
    public $newName;

    /**
     * @var string
     */
    public $oldName;

    /**
     * @var User
     */
    public $user;

    /**
     * Create a new event instance.
     *
     * @param  string  $newName
     * @param  string  $oldName
     * @return void
     */
    public function __construct(User $user, $oldName, $newName)
    {
        $this->newName = $newName;
        $this->oldName = $oldName;
        $this->user = $user;
    }
}
