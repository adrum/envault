<?php

namespace App\Jobs;

use Throwable;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $webhookId,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (!$webhook || !$webhook->active) {
            return;
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_SLASHES);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $webhook->secret);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $this->event,
            'url' => $webhook->url,
            'payload' => $this->payload,
            'attempt' => $this->attempts(),
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Envault-Webhook/1.0',
                    'X-Envault-Event' => $this->event,
                    'X-Envault-Signature' => $signature,
                    'X-Envault-Delivery' => (string) $delivery->id,
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $delivery->update([
                'response_status' => $response->status(),
                'response_body' => mb_substr((string) $response->body(), 0, 4000),
                'delivered_at' => now(),
            ]);

            if ($response->failed()) {
                throw new \RuntimeException("Webhook returned status {$response->status()}");
            }
        } catch (Throwable $e) {
            $delivery->update([
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'delivered_at' => now(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        WebhookDelivery::create([
            'webhook_id' => $this->webhookId,
            'event' => $this->event,
            'url' => '',
            'payload' => $this->payload,
            'attempt' => $this->tries,
            'error' => 'All attempts exhausted: ' . mb_substr($exception->getMessage(), 0, 800),
            'delivered_at' => now(),
        ]);
    }
}
