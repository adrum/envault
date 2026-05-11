<?php

use App\Models\App;
use App\Models\User;
use App\Models\Variable;
use App\Models\VariableVersion;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can create variable', function () {
    $app = App::factory()->create();
    $variableToCreate = Variable::factory()->make();
    $variableVersionToCreate = VariableVersion::factory()->make();

    $this->post(route('variables.store', $app), [
        'key' => $variableToCreate->key,
        'value' => $variableVersionToCreate->value,
    ])->assertRedirect();

    $this->assertDatabaseHas('variables', [
        'app_id' => $app->id,
        'key' => $variableToCreate->key,
    ]);

    expect($variableVersionToCreate->value)->toEqual(Variable::where([
        ['app_id', $app->id],
        ['key', $variableToCreate->key],
    ])->first()->latest_version->value);
});

test('can import variable', function () {
    $app = App::factory()->create();
    $variableToCreate = Variable::factory()->make();
    $variableVersionToCreate = VariableVersion::factory()->make();

    $this->post(route('variables.import', $app), [
        'env_content' => $variableToCreate->key . '=' . $variableVersionToCreate->value,
    ])->assertRedirect();

    $this->assertDatabaseHas('variables', [
        'app_id' => $app->id,
        'key' => $variableToCreate->key,
    ]);

    expect($variableVersionToCreate->value)->toEqual(Variable::where([
        ['app_id', $app->id],
        ['key', $variableToCreate->key],
    ])->first()->latest_version->value);
});

test('can import variable with excess whitespace', function () {
    $app = App::factory()->create();
    $variableToCreate = Variable::factory()->make();
    $variableVersionToCreate = VariableVersion::factory()->make();

    $this->post(route('variables.import', $app), [
        'env_content' => $variableToCreate->key . ' = ' . $variableVersionToCreate->value,
    ])->assertRedirect();

    $this->assertDatabaseHas('variables', [
        'app_id' => $app->id,
        'key' => $variableToCreate->key,
    ]);
});

test('can import variable with double-quoted multiline value', function () {
    $app = App::factory()->create();
    $environmentId = $app->environments()->value('environments.id');
    $pem = "-----BEGIN RSA PRIVATE KEY-----\nMIIBOgIBAAJBAKj==\n-----END RSA PRIVATE KEY-----";

    $this->post(route('variables.import', $app), [
        'env_content' => "PASSPORT_PRIVATE_KEY=\"{$pem}\"\nFOLLOWUP=ok",
        'environment_id' => $environmentId,
    ])->assertRedirect();

    $variable = Variable::where('app_id', $app->id)->where('key', 'PASSPORT_PRIVATE_KEY')->first();
    expect($variable->latest_version->value)->toEqual($pem);
    expect(Variable::where('app_id', $app->id)->where('key', 'FOLLOWUP')->first()->latest_version->value)
        ->toEqual('ok');
});

test('can import variable with single-quoted multiline value', function () {
    $app = App::factory()->create();
    $pem = "-----BEGIN PUBLIC KEY-----\nABC\n-----END PUBLIC KEY-----";

    $this->post(route('variables.import', $app), [
        'env_content' => "PASSPORT_PUBLIC_KEY='{$pem}'",
    ])->assertRedirect();

    expect(Variable::where('app_id', $app->id)->where('key', 'PASSPORT_PUBLIC_KEY')->first()->latest_version->value)
        ->toEqual($pem);
});

test('export round-trips a multiline value', function () {
    $app = App::factory()->create();
    $environmentId = $app->environments()->value('environments.id');
    $pem = "-----BEGIN RSA PRIVATE KEY-----\nMIIB\n-----END RSA PRIVATE KEY-----";

    $this->post(route('variables.import', $app), [
        'env_content' => "KEY=\"{$pem}\"",
        'environment_id' => $environmentId,
    ])->assertRedirect();

    $exported = $this->get(route('variables.export', ['app' => $app, 'environment' => $environmentId]))
        ->getContent();

    $reparsed = \App\Support\EnvFile::parse($exported);
    expect($reparsed)->toEqual([['key' => 'KEY', 'value' => $pem]]);
});

test('import removes variables that are not in the content', function () {
    $app = App::factory()->create();
    $environmentId = $app->environments()->value('environments.id');

    $keptVariable = $app->variables()->create(
        Variable::factory()->make(['environment_id' => $environmentId, 'key' => 'KEEP_ME'])->toArray(),
    );
    $removedVariable = $app->variables()->create(
        Variable::factory()->make(['environment_id' => $environmentId, 'key' => 'REMOVE_ME'])->toArray(),
    );

    $this->post(route('variables.import', $app), [
        'env_content' => 'KEEP_ME=stillhere',
        'environment_id' => $environmentId,
    ])->assertRedirect();

    $this->assertDatabaseHas('variables', ['id' => $keptVariable->id, 'deleted_at' => null]);
    $this->assertSoftDeleted('variables', ['id' => $removedVariable->id]);
});

test('key is alpha dash', function () {
    $app = App::factory()->create();

    $this->post(route('variables.store', $app), [
        'key' => fake()->sentence(),
        'value' => '',
    ])->assertSessionHasErrors('key');
});

test('key is required', function () {
    $app = App::factory()->create();

    $this->post(route('variables.store', $app))->assertSessionHasErrors('key');
});

test('same key is allowed across different environments of an app', function () {
    $app = App::factory()->create();
    $type = \App\Models\EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);
    $otherEnv = $app->environments()->create([
        'environment_type_id' => $type->id,
        'label' => $type->name,
        'color' => $type->color,
    ]);

    $variable = $app->variables()->create(Variable::factory()->make()->toArray());

    $this->post(route('variables.store', $app), [
        'key' => $variable->key,
        'environment_id' => $otherEnv->id,
        'value' => 'other-env-value',
    ])->assertSessionHasNoErrors();

    expect(Variable::where('app_id', $app->id)->where('key', $variable->key)->count())->toBe(2);
});

test('key is app unique', function () {
    $app = App::factory()->create();
    $variable = $app->variables()->create(Variable::factory()->make()->toArray());

    $this->post(route('variables.store', $app), [
        'key' => $variable->key,
    ])->assertSessionHasErrors('key');

    $variableBelongingToDifferentApp = App::factory()->create()->variables()->create(Variable::factory()->make()->toArray());

    $this->post(route('variables.store', $app), [
        'key' => $variableBelongingToDifferentApp->key,
    ])->assertSessionHasNoErrors();
});
