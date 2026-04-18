<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Variable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;

class VariableCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Variable $variable,
        public User $user,
    ) {}

    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        $app = $notifiable;

        return (new SlackMessage)
            ->content("{$this->user->name} added a new variable \"{$this->variable->key}\" to the {$app->name} app.")
            ->to($app->slack_notification_channel);
    }
}
