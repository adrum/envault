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
