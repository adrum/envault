<?php

namespace App\Events\Variables;

use App\Models\App;
use App\Models\Variable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class DeletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var App
     */
    public $app;

    /**
     * @var Variable
     */
    public $variable;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(App $app, Variable $variable)
    {
        $this->app = $app;
        $this->variable = $variable;
    }
}
