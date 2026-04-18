<?php

namespace App\Listeners\Variables;

use App\Events\Variables\CreatedEvent;
use App\Notifications\VariableCreatedNotification;

class NotifyOfVariableCreationListener
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(CreatedEvent $event)
    {
        if ($event->app->notificationsEnabled()) {
            $event->app->notify(new VariableCreatedNotification($event->variable, $event->user));
        }
    }
}
