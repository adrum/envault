<?php

use App\Models\App;
use App\Models\User;
use GuzzleHttp\Client;
use App\Models\Variable;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use App\Notifications\VariableCreatedNotification;

/*
 * The legacy Slack webhook channel posts through a raw Guzzle client rather
 * than Laravel's HTTP client, so Http::fake() cannot see it. Binding a Guzzle
 * client backed by a mock handler exercises the real channel, Guzzle and PSR-7
 * stack end to end.
 */
test('slack notifications are posted to the app webhook', function () {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], 'ok')]));
    $stack->push(Middleware::history($history));
    $this->app->instance(Client::class, new Client(['handler' => $stack]));

    $app = App::factory()->create([
        'name' => 'Billing',
        'slack_notification_channel' => '#deploys',
        'slack_notification_webhook_url' => 'https://hooks.slack.com/services/T000/B000/XXXX',
    ]);
    $variable = $app->variables()->create(Variable::factory()->make(['key' => 'STRIPE_KEY'])->toArray());
    $user = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

    $app->notify(new VariableCreatedNotification($variable, $user));

    expect($history)->toHaveCount(1);

    $request = $history[0]['request'];
    $payload = json_decode((string) $request->getBody(), true);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://hooks.slack.com/services/T000/B000/XXXX')
        ->and($payload['channel'])->toBe('#deploys')
        ->and($payload['text'])->toBe('Ada Lovelace added a new variable "STRIPE_KEY" to the Billing app.');
});
