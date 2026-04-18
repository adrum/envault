<?php

namespace App\Events\Apps;

use App\Models\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class NameUpdatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var App
     */
    public $app;

    /**
     * @var string
     */
    public $newName;

    /**
     * @var string
     */
    public $oldName;

    /**
     * Create a new event instance.
     *
     * @param  string  $oldName
     * @param  string  $newName
     * @return void
     */
    public function __construct(App $app, $oldName, $newName)
    {
        $this->app = $app;
        $this->newName = $newName;
        $this->oldName = $oldName;
    }
}
