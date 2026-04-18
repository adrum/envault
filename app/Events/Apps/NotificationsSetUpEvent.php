<?php

namespace App\Events\Apps;

use App\Models\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class NotificationsSetUpEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var App
     */
    public $app;

    /**
     * Create a new event instance.
     *
     * @param  App  $app
     * @return void
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }
}
