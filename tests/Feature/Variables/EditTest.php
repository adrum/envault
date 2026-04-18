<?php

use App\Models\App;
use App\Models\User;
use App\Models\Variable;
use App\Models\VariableVersion;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can update variable', function () {
    $app = App::factory()->create();
    $variableToUpdate = $app->variables()->create(Variable::factory()->make()->toArray());
    $newVariableDetails = Variable::factory()->make();
    $newVariableVersionDetails = VariableVersion::factory()->make();

    $this->patch(route('variables.update', $variableToUpdate), [
        'key' => $newVariableDetails->key,
        'value' => $newVariableVersionDetails->value,
    ])->assertRedirect();

    $this->assertDatabaseHas('variables', [
        'app_id' => $app->id,
        'key' => $newVariableDetails->key,
    ]);

    expect($newVariableVersionDetails->value)->toEqual(Variable::where([
        ['app_id', $app->id],
        ['key', $newVariableDetails->key],
    ])->first()->latest_version->value);
});

test('key is alpha dash', function () {
    $app = App::factory()->create();
    $variableToUpdate = $app->variables()->create(Variable::factory()->make()->toArray());

    $this->patch(route('variables.update', $variableToUpdate), [
        'key' => fake()->sentence(),
    ])->assertSessionHasErrors('key');
});

test('key is app unique', function () {
    $app = App::factory()->create();
    $variable = $app->variables()->create(Variable::factory()->make()->toArray());
    $variableToUpdate = $app->variables()->create(Variable::factory()->make()->toArray());

    $this->patch(route('variables.update', $variableToUpdate), [
        'key' => $variable->key,
    ])->assertSessionHasErrors('key');

    $variableBelongingToDifferentApp = App::factory()->create()->variables()->create(Variable::factory()->make()->toArray());

    $this->patch(route('variables.update', $variableToUpdate), [
        'key' => $variableBelongingToDifferentApp->key,
    ])->assertSessionHasNoErrors();
});
