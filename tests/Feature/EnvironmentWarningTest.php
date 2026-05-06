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

it('returns no warnings for a clean local environment', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => [
            'APP_DEBUG' => 'true',
            'APP_ENV' => 'local',
            'LOG_LEVEL' => 'debug',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns when APP_DEBUG=true outside of local', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => ['APP_DEBUG' => 'true'],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'warnings')
        ->assertJsonPath('warnings.0.keys', ['APP_DEBUG']);
});

it('does not warn for APP_DEBUG=false in production', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => ['APP_DEBUG' => 'false'],
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns when SESSION_SECURE_COOKIE=false in production', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => ['SESSION_SECURE_COOKIE' => 'false'],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'warnings')
        ->assertJsonPath('warnings.0.keys', ['SESSION_SECURE_COOKIE']);
});

it('does not warn for SESSION_SECURE_COOKIE=false outside production', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->local->id), [
        'values' => ['SESSION_SECURE_COOKIE' => 'false'],
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns on unknown MAIL_MAILER values', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => ['MAIL_MAILER' => 'mailgunny'],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'warnings')
        ->assertJsonPath('warnings.0.keys', ['MAIL_MAILER']);
});

it('does not warn on known MAIL_MAILER values', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => ['MAIL_MAILER' => 'smtp'],
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns when MAIL_MAILER=mailgun is missing companion keys', function () {
    $response = $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => ['MAIL_MAILER' => 'mailgun'],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'warnings');

    $warning = $response->json('warnings.0');

    expect($warning['keys'])->toContain('MAIL_MAILER', 'MAILGUN_DOMAIN', 'MAILGUN_SECRET');
    expect($warning['message'])->toContain('Missing');
});

it('does not warn when companions are present and non-empty', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => [
            'MAIL_MAILER' => 'mailgun',
            'MAILGUN_DOMAIN' => 'mg.example.com',
            'MAILGUN_SECRET' => 'key-xxx',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('warnings', []);
});

it('warns when companions are present but empty', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => [
            'MAIL_MAILER' => 'mailgun',
            'MAILGUN_DOMAIN' => '',
            'MAILGUN_SECRET' => '',
        ],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'warnings');
});

it('returns multiple warnings when multiple rules match', function () {
    $this->postJson(preflightUrl($this->testApp->id, $this->production->id), [
        'values' => [
            'APP_DEBUG' => 'true',
            'LOG_LEVEL' => 'debug',
            'MAIL_MAILER' => 'unknown-driver',
        ],
    ])
        ->assertOk()
        ->assertJsonCount(3, 'warnings');
});

it('rejects preflight from users without permission', function () {
    $stranger = User::factory()->create(['role' => UserRole::USER]);

    $this->actingAs($stranger)
        ->postJson(preflightUrl($this->testApp->id, $this->production->id), [
            'values' => ['APP_DEBUG' => 'true'],
        ])
        ->assertForbidden();
});

it('returns 404 when environment does not belong to app', function () {
    $otherApp = App::create(['name' => 'Other']);

    $this->postJson(preflightUrl($otherApp->id, $this->production->id), [
        'values' => ['APP_DEBUG' => 'true'],
    ])
        ->assertNotFound();
});
