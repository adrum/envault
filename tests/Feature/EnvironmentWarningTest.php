<?php

use App\Models\App;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\EnvironmentType;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($this->user);

    $this->localType = EnvironmentType::firstOrCreate(
        ['name' => 'Local'],
        ['color' => 'gray', 'sort_order' => 0]
    );

    $this->productionType = EnvironmentType::firstOrCreate(
        ['name' => 'Production'],
        ['color' => 'red', 'sort_order' => 1]
    );

    $this->testApp = App::create(['name' => 'Test App']);

    $this->local = Environment::where('app_id', $this->testApp->id)->first();
    $this->local->update([
        'environment_type_id' => $this->localType->id,
        'label' => 'Local',
        'slug' => 'local',
    ]);

    $this->production = $this->testApp->environments()->create([
        'environment_type_id' => $this->productionType->id,
        'label' => 'Production',
    ]);
});

function preflightUrl(int $appId, int $envId): string
{
    return "/apps/{$appId}/environments/{$envId}/warnings";
}

/**
 * Baseline values that satisfy the broad "always-on" rules (APP_KEY empty,
 * DB_PASSWORD empty in non-local). Tests merge their specific scenario on top.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function baseline(array $overrides = []): array
{
    return array_merge([
        'APP_KEY' => 'base64:abc123',
        'DB_PASSWORD' => 'pw',
    ], $overrides);
}

function warningKeys(Illuminate\Testing\TestResponse $response): array
{
    return collect($response->json('warnings'))
        ->flatMap(fn ($w) => $w['keys'])
        ->values()
        ->all();
}

it('returns no warnings for a clean local environment', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => baseline([
            'APP_DEBUG' => 'true',
            'APP_ENV' => 'local',
            'LOG_LEVEL' => 'debug',
        ]),
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns when APP_DEBUG=true outside of local', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['APP_DEBUG' => 'true']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('APP_DEBUG');
});

it('does not warn for APP_DEBUG=false in production', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['APP_DEBUG' => 'false']),
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns when SESSION_SECURE_COOKIE=false in production', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['SESSION_SECURE_COOKIE' => 'false']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('SESSION_SECURE_COOKIE');
});

it('does not warn for SESSION_SECURE_COOKIE=false outside production', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => baseline(['SESSION_SECURE_COOKIE' => 'false']),
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns on unknown MAIL_MAILER values', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['MAIL_MAILER' => 'mailgunny']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('MAIL_MAILER');
});

it('does not warn on known MAIL_MAILER values', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['MAIL_MAILER' => 'smtp']),
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns when MAIL_MAILER=mailgun is missing companion keys', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['MAIL_MAILER' => 'mailgun']),
    ])->assertOk();

    $warning = collect($response->json('warnings'))
        ->firstWhere(fn ($w) => in_array('MAIL_MAILER', $w['keys']));

    expect($warning)->not->toBeNull();
    expect($warning['keys'])->toContain('MAIL_MAILER', 'MAILGUN_DOMAIN', 'MAILGUN_SECRET');
    expect($warning['message'])->toContain('Missing');
});

it('does not warn when companions are present and non-empty', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline([
            'MAIL_MAILER' => 'mailgun',
            'MAILGUN_DOMAIN' => 'mg.example.com',
            'MAILGUN_SECRET' => 'key-xxx',
        ]),
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns when companions are present but empty', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline([
            'MAIL_MAILER' => 'mailgun',
            'MAILGUN_DOMAIN' => '',
            'MAILGUN_SECRET' => '',
        ]),
    ])->assertOk();

    expect(warningKeys($response))->toContain('MAIL_MAILER');
});

it('returns multiple warnings when multiple rules match', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline([
            'APP_DEBUG' => 'true',
            'LOG_LEVEL' => 'debug',
            'MAIL_MAILER' => 'unknown-driver',
        ]),
    ])->assertOk();

    $keys = warningKeys($response);
    expect($keys)->toContain('APP_DEBUG');
    expect($keys)->toContain('LOG_LEVEL');
    expect($keys)->toContain('MAIL_MAILER');
});

it('rejects preflight from users without permission', function () {
    $stranger = User::factory()->create(['role' => UserRole::USER]);

    $this->actingAs($stranger)
        ->postJson(preflightUrl($this->testApp->id, $this->production->id), [
            'values' => ['APP_DEBUG' => 'true'],
        ])
        ->assertForbidden();
});

it('warns when APP_KEY is empty', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => baseline(['APP_KEY' => '']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('APP_KEY');
});

it('warns when APP_KEY is missing entirely', function () {
    $values = baseline();
    unset($values['APP_KEY']);

    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => $values,
    ])->assertOk();

    expect(warningKeys($response))->toContain('APP_KEY');
});

it('does not warn when APP_KEY has a value', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => baseline(),
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns when DB_PASSWORD is empty in production', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['DB_PASSWORD' => '']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('DB_PASSWORD');
});

it('does not warn when DB_PASSWORD is empty in local', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => baseline(['DB_PASSWORD' => '']),
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns on localhost APP_URL outside local', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['APP_URL' => 'http://localhost']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('APP_URL');
});

it('warns on SESSION_DRIVER=array outside local', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['SESSION_DRIVER' => 'array']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('SESSION_DRIVER');
});

it('warns on CACHE_STORE=array outside local', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['CACHE_STORE' => 'array']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('CACHE_STORE');
});

it('warns on LOG_CHANNEL=single in production', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => baseline(['LOG_CHANNEL' => 'single']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('LOG_CHANNEL');
});

it('warns on placeholder MAIL_FROM_ADDRESS', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => baseline(['MAIL_FROM_ADDRESS' => 'hello@example.com']),
    ])->assertOk();

    expect(warningKeys($response))->toContain('MAIL_FROM_ADDRESS');
});

it('warns on generic placeholder values across any key', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => baseline([
            'STRIPE_SECRET' => 'changeme',
            'GITHUB_TOKEN' => 'your-key-here',
        ]),
    ])->assertOk();

    $keys = warningKeys($response);
    expect($keys)->toContain('STRIPE_SECRET');
    expect($keys)->toContain('GITHUB_TOKEN');
});

it('accepts cloudflare as a valid MAIL_MAILER', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => baseline(['MAIL_MAILER' => 'cloudflare']),
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('returns 404 when environment does not belong to app', function () {
    $otherApp = App::create(['name' => 'Other']);

    $this->postJson(preflightUrl($otherApp->id, $this->production->id), [
        'values' => ['APP_DEBUG' => 'true'],
    ])
        ->assertNotFound();
});
