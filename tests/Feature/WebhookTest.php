<?php

use App\Enums\UserRole;
use App\Jobs\DispatchWebhookJob;
use App\Models\App;
use App\Models\Environment;
use App\Models\EnvironmentType;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\Webhooks\WebhookDispatcher;
use App\Support\Webhooks\WebhookEvents;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->user = User::factory()->create(['role' => UserRole::USER]);

    $this->localType = EnvironmentType::firstOrCreate(
        ['name' => 'Local'],
        ['color' => 'gray', 'sort_order' => 0],
    );
    $this->productionType = EnvironmentType::firstOrCreate(
        ['name' => 'Production'],
        ['color' => 'red', 'sort_order' => 1],
    );

    $this->testApp = App::create(['name' => 'Test App']);
    $this->local = Environment::where('app_id', $this->testApp->id)->first();
    $this->local->update(['environment_type_id' => $this->localType->id]);

    $this->production = $this->testApp->environments()->create([
        'environment_type_id' => $this->productionType->id,
        'label' => 'Production',
    ]);
});

it('blocks non-admin users from the index page', function () {
    $this->actingAs($this->user)
        ->get('/settings/webhooks')
        ->assertForbidden();
});

it('lets admins view the index page', function () {
    $this->actingAs($this->owner)
        ->get('/settings/webhooks')
        ->assertOk();
});

it('creates a webhook with subscriptions', function () {
    $this->actingAs($this->owner)
        ->post('/settings/webhooks', [
            'name' => 'Slack',
            'url' => 'https://example.com/hook',
            'events' => [WebhookEvents::VARIABLE_UPDATED],
            'active' => true,
            'subscriptions' => [
                ['type' => 'app', 'id' => $this->testApp->id],
                ['type' => 'environment_type', 'id' => $this->productionType->id],
            ],
        ])
        ->assertRedirect('/settings/webhooks');

    $webhook = Webhook::where('name', 'Slack')->firstOrFail();
    expect($webhook->subscriptions)->toHaveCount(2);
    expect($webhook->secret)->not->toBeEmpty();
});

it('rejects creating a webhook without subscriptions or events', function () {
    $this->actingAs($this->owner)
        ->postJson('/settings/webhooks', [
            'name' => 'Empty',
            'url' => 'https://example.com',
            'events' => [],
            'subscriptions' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['events', 'subscriptions']);
});

it('matches scope by app subscription', function () {
    Queue::fake();

    $webhook = makeWebhook([WebhookEvents::VARIABLE_CREATED], [
        ['type' => 'app', 'id' => $this->testApp->id],
    ]);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvents::VARIABLE_CREATED,
        $this->testApp,
        $this->local,
        $this->owner,
    );

    Queue::assertPushed(DispatchWebhookJob::class, fn ($job) => $job->webhookId === $webhook->id);
});

it('matches scope by environment subscription', function () {
    Queue::fake();

    $webhook = makeWebhook([WebhookEvents::VARIABLE_UPDATED], [
        ['type' => 'environment', 'id' => $this->production->id],
    ]);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvents::VARIABLE_UPDATED,
        $this->testApp,
        $this->production,
        $this->owner,
    );

    Queue::assertPushed(DispatchWebhookJob::class, fn ($job) => $job->webhookId === $webhook->id);
});

it('matches scope by environment_type subscription', function () {
    Queue::fake();

    $webhook = makeWebhook([WebhookEvents::VARIABLE_UPDATED], [
        ['type' => 'environment_type', 'id' => $this->productionType->id],
    ]);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvents::VARIABLE_UPDATED,
        $this->testApp,
        $this->production,
        $this->owner,
    );

    Queue::assertPushed(DispatchWebhookJob::class, fn ($job) => $job->webhookId === $webhook->id);
});

it('does not dispatch when scope does not match', function () {
    Queue::fake();

    makeWebhook([WebhookEvents::VARIABLE_UPDATED], [
        ['type' => 'environment', 'id' => $this->local->id],
    ]);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvents::VARIABLE_UPDATED,
        $this->testApp,
        $this->production,
        $this->owner,
    );

    Queue::assertNothingPushed();
});

it('does not dispatch for unsubscribed events', function () {
    Queue::fake();

    makeWebhook([WebhookEvents::VARIABLE_CREATED], [
        ['type' => 'app', 'id' => $this->testApp->id],
    ]);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvents::VARIABLE_DELETED,
        $this->testApp,
        $this->production,
        $this->owner,
    );

    Queue::assertNothingPushed();
});

it('skips inactive webhooks', function () {
    Queue::fake();

    $webhook = makeWebhook([WebhookEvents::VARIABLE_UPDATED], [
        ['type' => 'app', 'id' => $this->testApp->id],
    ]);
    $webhook->update(['active' => false]);

    app(WebhookDispatcher::class)->dispatch(
        WebhookEvents::VARIABLE_UPDATED,
        $this->testApp,
        $this->production,
        $this->owner,
    );

    Queue::assertNothingPushed();
});

it('queues a test event regardless of scope', function () {
    Queue::fake();

    $webhook = makeWebhook([WebhookEvents::VARIABLE_UPDATED], [
        ['type' => 'app', 'id' => $this->testApp->id],
    ]);

    $this->actingAs($this->owner)
        ->post("/settings/webhooks/{$webhook->id}/test")
        ->assertRedirect();

    Queue::assertPushed(DispatchWebhookJob::class, fn ($job) => $job->event === WebhookEvents::TEST);
});

it('records a successful delivery', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    $webhook = makeWebhook([WebhookEvents::VARIABLE_UPDATED], [
        ['type' => 'app', 'id' => $this->testApp->id],
    ]);

    (new DispatchWebhookJob($webhook->id, WebhookEvents::VARIABLE_UPDATED, ['hello' => 'world']))->handle();

    $delivery = WebhookDelivery::where('webhook_id', $webhook->id)->first();
    expect($delivery)->not->toBeNull();
    expect($delivery->response_status)->toBe(200);
    expect($delivery->error)->toBeNull();
    expect($delivery->isSuccess())->toBeTrue();
});

it('signs the request with the webhook secret', function () {
    Http::fake();

    $webhook = makeWebhook([WebhookEvents::VARIABLE_UPDATED], [
        ['type' => 'app', 'id' => $this->testApp->id],
    ]);

    (new DispatchWebhookJob($webhook->id, WebhookEvents::TEST, ['x' => 1]))->handle();

    Http::assertSent(function ($req) use ($webhook) {
        $body = $req->body();
        $expected = 'sha256=' . hash_hmac('sha256', $body, $webhook->secret);

        return $req->hasHeader('X-Envault-Signature', $expected)
            && $req->hasHeader('X-Envault-Event', WebhookEvents::TEST);
    });
});

it('records a failed delivery and re-throws for retry', function () {
    Http::fake([
        '*' => Http::response('boom', 500),
    ]);

    $webhook = makeWebhook([WebhookEvents::VARIABLE_UPDATED], [
        ['type' => 'app', 'id' => $this->testApp->id],
    ]);

    expect(fn () => (new DispatchWebhookJob($webhook->id, WebhookEvents::TEST, []))->handle())
        ->toThrow(\RuntimeException::class);

    $delivery = WebhookDelivery::where('webhook_id', $webhook->id)->first();
    expect($delivery->response_status)->toBe(500);
    expect($delivery->isSuccess())->toBeFalse();
});

it('dispatches webhooks when a variable is created', function () {
    Queue::fake();

    makeWebhook([WebhookEvents::VARIABLE_CREATED], [
        ['type' => 'app', 'id' => $this->testApp->id],
    ]);

    $this->actingAs($this->owner)
        ->post("/apps/{$this->testApp->id}/variables", [
            'key' => 'NEW_VAR',
            'value' => 'value',
            'environment_id' => $this->local->id,
        ])
        ->assertRedirect();

    Queue::assertPushed(DispatchWebhookJob::class);
});

function makeWebhook(array $events, array $subscriptions): Webhook
{
    $webhook = Webhook::create([
        'name' => 'test-' . uniqid(),
        'url' => 'https://example.com/hook',
        'events' => $events,
        'active' => true,
        'secret' => 'test-secret',
        'created_by' => test()->owner->id,
    ]);

    $typeMap = [
        'app' => App::class,
        'environment' => Environment::class,
        'environment_type' => EnvironmentType::class,
    ];

    foreach ($subscriptions as $sub) {
        $webhook->subscriptions()->create([
            'subscribable_type' => $typeMap[$sub['type']],
            'subscribable_id' => $sub['id'],
        ]);
    }

    return $webhook->fresh('subscriptions');
}
