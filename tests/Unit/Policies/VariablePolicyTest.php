<?php

use App\Models\App;
use App\Models\User;
use App\Models\Variable;
use App\Policies\VariablePolicy;

test('admin or owner can delete variables', function () {
    $app = App::factory()->create();

    $variableToDelete = $app->variables()->create(Variable::factory()->make()->toArray());

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new VariablePolicy)->delete($user, $variableToDelete))->toBeTrue();

    $user->role = 'owner';

    expect((new VariablePolicy)->delete($user, $variableToDelete))->toBeTrue();
});

test('app admin can delete variables', function () {
    $app = App::factory()->create();

    $variableToDelete = $app->variables()->create(Variable::factory()->make()->toArray());

    $user = User::factory()->create();

    $app->collaborators()->attach($user, [
        'role' => 'admin',
    ]);

    expect((new VariablePolicy)->delete($user, $variableToDelete))->toBeTrue();
});

test('not admin or owner or app admin cant delete variables', function () {
    $app = App::factory()->create();

    $variableToDelete = $app->variables()->create(Variable::factory()->make()->toArray());

    $user = User::factory()->create();

    expect((new VariablePolicy)->delete($user, $variableToDelete))->toBeFalse();

    $app->collaborators()->attach($user);

    expect((new VariablePolicy)->delete($user, $variableToDelete))->toBeFalse();
});

test('admin or owner can update variables', function () {
    $app = App::factory()->create();

    $variableToUpdate = $app->variables()->create(Variable::factory()->make()->toArray());

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new VariablePolicy)->update($user, $variableToUpdate))->toBeTrue();

    $user->role = 'owner';

    expect((new VariablePolicy)->update($user, $variableToUpdate))->toBeTrue();
});

test('app admin can update variables', function () {
    $app = App::factory()->create();

    $variableToUpdate = $app->variables()->create(Variable::factory()->make()->toArray());

    $user = User::factory()->create();

    $app->collaborators()->attach($user, [
        'role' => 'admin',
    ]);

    expect((new VariablePolicy)->update($user, $variableToUpdate))->toBeTrue();
});

test('not admin or owner or app admin cant update variables', function () {
    $app = App::factory()->create();

    $variableToUpdate = $app->variables()->create(Variable::factory()->make()->toArray());

    $user = User::factory()->create();

    expect((new VariablePolicy)->update($user, $variableToUpdate))->toBeFalse();

    $app->collaborators()->attach($user);

    expect((new VariablePolicy)->update($user, $variableToUpdate))->toBeFalse();
});

test('admin or owner can view variable', function () {
    $app = App::factory()->create();

    $variableToView = $app->variables()->create(Variable::factory()->make()->toArray());

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new VariablePolicy)->view($user, $variableToView))->toBeTrue();

    $user->role = 'owner';

    expect((new VariablePolicy)->view($user, $variableToView))->toBeTrue();
});

test('app collaborator can view variable', function () {
    $app = App::factory()->create();

    $variableToView = $app->variables()->create(Variable::factory()->make()->toArray());

    $user = User::factory()->create();

    $app->collaborators()->attach($user);

    expect((new VariablePolicy)->view($user, $variableToView))->toBeTrue();
});

test('not admin or owner or app collaborator cant view variable', function () {
    $app = App::factory()->create();

    $variableToView = $app->variables()->create(Variable::factory()->make()->toArray());

    $user = User::factory()->create();

    expect((new VariablePolicy)->view($user, $variableToView))->toBeFalse();
});
