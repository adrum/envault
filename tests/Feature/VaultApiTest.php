<?php

use App\Models\App;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\EnvironmentType;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => UserRole::OWNER]);
    $this->token = $this->user->createToken('test')->plainTextToken;

    $this->type = EnvironmentType::firstOrCreate(
        ['name' => 'Default'],
        ['color' => 'gray', 'sort_order' => 0]
    );
});

it('returns health check without auth', function () {
    $this->getJson('/api/v1/sys/health')
        ->assertOk()
        ->assertJsonFragment(['initialized' => true, 'sealed' => false]);
});

it('rejects requests without token', function () {
    $this->getJson('/api/v1/auth/token/lookup-self')
        ->assertStatus(403);
});

it('validates token via X-Vault-Token header', function () {
    $this->getJson('/api/v1/auth/token/lookup-self', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.display_name', $this->user->name);
});

it('lists apps the user can access', function () {
    $app = App::create(['name' => 'Test App']);
    $app->environments()->create([
        'environment_type_id' => $this->type->id,
        'label' => 'Default',
    ]);

    $this->getJson('/api/v1/secret/metadata/', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.keys', [$app->slug . '/']);
});

it('lists environments for an app', function () {
    $app = App::create(['name' => 'Test App']);
    $app->environments()->create([
        'environment_type_id' => $this->type->id,
        'label' => 'Default',
    ]);

    $this->getJson('/api/v1/secret/metadata/' . $app->slug, [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonFragment(['keys' => ['default']]);
});

it('reads secrets for an app environment', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();
    $variable = $env->variables()->create([
        'app_id' => $app->id,
        'key' => 'DB_HOST',
        'sort_order' => 0,
    ]);
    $variable->versions()->create(['value' => 'localhost']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.data.DB_HOST', 'localhost');
});

it('returns 404 for unknown app slug', function () {
    $this->getJson('/api/v1/secret/data/nonexistent/default', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertNotFound();
});

it('returns 404 for unknown environment', function () {
    $app = App::create(['name' => 'Test App']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/nonexistent', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertNotFound();
});
