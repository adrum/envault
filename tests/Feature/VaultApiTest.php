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

it('expands ${VAR} references against sibling variables', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $host = $env->variables()->create(['app_id' => $app->id, 'key' => 'APP_HOST', 'sort_order' => 0]);
    $host->versions()->create(['value' => 'example.com']);

    $url = $env->variables()->create(['app_id' => $app->id, 'key' => 'APP_URL', 'sort_order' => 1]);
    $url->versions()->create(['value' => 'https://${APP_HOST}/api']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.data.APP_URL', 'https://example.com/api');
});

it('resolves chained references and leaves unresolvable refs intact', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $a = $env->variables()->create(['app_id' => $app->id, 'key' => 'A', 'sort_order' => 0]);
    $a->versions()->create(['value' => 'one']);

    $b = $env->variables()->create(['app_id' => $app->id, 'key' => 'B', 'sort_order' => 1]);
    $b->versions()->create(['value' => '${A}-two']);

    $c = $env->variables()->create(['app_id' => $app->id, 'key' => 'C', 'sort_order' => 2]);
    $c->versions()->create(['value' => '${B}-${MISSING}']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.data.B', 'one-two')
        ->assertJsonPath('data.data.C', 'one-two-${MISSING}');
});

it('does not infinite loop on self-reference', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $foo = $env->variables()->create(['app_id' => $app->id, 'key' => 'FOO', 'sort_order' => 0]);
    $foo->versions()->create(['value' => '${FOO}']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])->assertOk();
});

it('expands multiple references in a single value', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $scheme = $env->variables()->create(['app_id' => $app->id, 'key' => 'SCHEME', 'sort_order' => 0]);
    $scheme->versions()->create(['value' => 'https']);

    $host = $env->variables()->create(['app_id' => $app->id, 'key' => 'HOST', 'sort_order' => 1]);
    $host->versions()->create(['value' => 'example.com']);

    $port = $env->variables()->create(['app_id' => $app->id, 'key' => 'PORT', 'sort_order' => 2]);
    $port->versions()->create(['value' => '8443']);

    $url = $env->variables()->create(['app_id' => $app->id, 'key' => 'URL', 'sort_order' => 3]);
    $url->versions()->create(['value' => '${SCHEME}://${HOST}:${PORT}']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.data.URL', 'https://example.com:8443');
});

it('expands references to empty values', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $empty = $env->variables()->create(['app_id' => $app->id, 'key' => 'EMPTY', 'sort_order' => 0]);
    $empty->versions()->create(['value' => '']);

    $wrap = $env->variables()->create(['app_id' => $app->id, 'key' => 'WRAP', 'sort_order' => 1]);
    $wrap->versions()->create(['value' => '[${EMPTY}]']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.data.WRAP', '[]');
});

it('expands references to variables with no version yet', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $env->variables()->create(['app_id' => $app->id, 'key' => 'UNVERSIONED', 'sort_order' => 0]);

    $consumer = $env->variables()->create(['app_id' => $app->id, 'key' => 'CONSUMER', 'sort_order' => 1]);
    $consumer->versions()->create(['value' => 'prefix-${UNVERSIONED}-suffix']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.data.CONSUMER', 'prefix--suffix');
});

it('expands adjacent references with no separator', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $a = $env->variables()->create(['app_id' => $app->id, 'key' => 'A', 'sort_order' => 0]);
    $a->versions()->create(['value' => 'foo']);

    $b = $env->variables()->create(['app_id' => $app->id, 'key' => 'B', 'sort_order' => 1]);
    $b->versions()->create(['value' => 'bar']);

    $combined = $env->variables()->create(['app_id' => $app->id, 'key' => 'COMBINED', 'sort_order' => 2]);
    $combined->versions()->create(['value' => '${A}${B}']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.data.COMBINED', 'foobar');
});

it('preserves literal dollar signs that are not references', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $pw = $env->variables()->create(['app_id' => $app->id, 'key' => 'PASSWORD', 'sort_order' => 0]);
    $pw->versions()->create(['value' => 'p@ss$word$123']);

    $bare = $env->variables()->create(['app_id' => $app->id, 'key' => 'BARE', 'sort_order' => 1]);
    $bare->versions()->create(['value' => '$NOT_A_REF']);

    $response = $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])->assertOk();

    $response->assertJsonPath('data.data.PASSWORD', 'p@ss$word$123');
    $response->assertJsonPath('data.data.BARE', '$NOT_A_REF');
});

it('resolves deeply chained references', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $a = $env->variables()->create(['app_id' => $app->id, 'key' => 'A', 'sort_order' => 0]);
    $a->versions()->create(['value' => 'root']);

    $b = $env->variables()->create(['app_id' => $app->id, 'key' => 'B', 'sort_order' => 1]);
    $b->versions()->create(['value' => '${A}/b']);

    $c = $env->variables()->create(['app_id' => $app->id, 'key' => 'C', 'sort_order' => 2]);
    $c->versions()->create(['value' => '${B}/c']);

    $d = $env->variables()->create(['app_id' => $app->id, 'key' => 'D', 'sort_order' => 3]);
    $d->versions()->create(['value' => '${C}/d']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.data.D', 'root/b/c/d');
});

it('does not infinite loop on circular references', function () {
    $app = App::create(['name' => 'Test App']);
    $env = \App\Models\Environment::where('app_id', $app->id)->first();

    $x = $env->variables()->create(['app_id' => $app->id, 'key' => 'X', 'sort_order' => 0]);
    $x->versions()->create(['value' => '${Y}']);

    $y = $env->variables()->create(['app_id' => $app->id, 'key' => 'Y', 'sort_order' => 1]);
    $y->versions()->create(['value' => '${X}']);

    $this->getJson('/api/v1/secret/data/' . $app->slug . '/default', [
        'X-Vault-Token' => $this->token,
    ])->assertOk();
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
