<?php

namespace App\Support\Webhooks;

use App\Models\App;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Variable;
use App\Models\Environment;
use App\Jobs\DispatchWebhookJob;

class WebhookDispatcher
{
    /**
     * Find webhooks subscribed to this event whose scope matches the
     * affected app/environment, then queue a delivery for each.
     */
    public function dispatch(string $event, ?App $app, ?Environment $environment, ?User $actor, array $extra = []): void
    {
        $webhooks = Webhook::with('subscriptions')
            ->where('active', true)
            ->get()
            ->filter(fn (Webhook $w) => $w->matches($event, $app, $environment));

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = $this->buildPayload($event, $app, $environment, $actor, $extra);

        foreach ($webhooks as $webhook) {
            DispatchWebhookJob::dispatch($webhook->id, $event, $payload);
        }
    }

    /**
     * Dispatch a synthetic test event to a single webhook regardless of
     * subscription scope. Used by the "Send test event" button.
     */
    public function dispatchTest(Webhook $webhook, ?User $actor): void
    {
        $payload = $this->buildPayload(WebhookEvents::TEST, null, null, $actor, [
            'message' => 'This is a test event from Envault.',
        ]);

        DispatchWebhookJob::dispatch($webhook->id, WebhookEvents::TEST, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $event, ?App $app, ?Environment $environment, ?User $actor, array $extra): array
    {
        $variable = $extra['variable'] ?? null;

        return [
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'actor' => $actor ? [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
            ] : null,
            'app' => $app ? [
                'id' => $app->id,
                'name' => $app->name,
                'slug' => $app->slug,
            ] : null,
            'environment' => $environment ? [
                'id' => $environment->id,
                'label' => $environment->label,
                'slug' => $environment->slug,
                'environment_type' => $environment->environmentType?->name,
            ] : null,
            'variable' => $variable instanceof Variable ? [
                'id' => $variable->id,
                'key' => $variable->key,
            ] : null,
            'extra' => array_diff_key($extra, ['variable' => null]),
        ];
    }
}
