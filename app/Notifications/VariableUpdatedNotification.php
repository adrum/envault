<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Variable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;

class VariableUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Variable $variable,
        public User $user,
        public string $changeType = 'value',
    ) {}

    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        $app = $notifiable;

        $action = $this->changeType === 'key'
            ? "updated the key of variable \"{$this->variable->key}\""
            : "updated the value of variable \"{$this->variable->key}\"";

        return (new SlackMessage)
            ->content("{$this->user->name} {$action} in the {$app->name} app.")
            ->to($app->slack_notification_channel);
    }
}
