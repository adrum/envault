<?php

use App\Models\App;
use App\Models\User;
use App\Policies\AppPolicy;

test('admin or owner can create app', function () {
    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new AppPolicy)->create($user))->toBeTrue();

    $user->role = 'owner';

    expect((new AppPolicy)->create($user))->toBeTrue();
});

test('not admin or owner cant create app', function () {
    $user = User::factory()->create();

    expect((new AppPolicy)->create($user))->toBeFalse();
});

test('admin or owner can create variables', function () {
    $appToCreateVariablesFor = App::factory()->create();

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new AppPolicy)->createVariable($user, $appToCreateVariablesFor))->toBeTrue();

    $user->role = 'owner';

    expect((new AppPolicy)->createVariable($user, $appToCreateVariablesFor))->toBeTrue();
});

test('app admin can create variables', function () {
    $appToCreateVariablesFor = App::factory()->create();

    $user = User::factory()->create();

    $appToCreateVariablesFor->collaborators()->attach($user, [
        'role' => 'admin',
    ]);

    expect((new AppPolicy)->createVariable($user, $appToCreateVariablesFor))->toBeTrue();
});

test('not admin or owner or app admin cant create variables', function () {
    $appToCreateVariablesFor = App::factory()->create();

    $user = User::factory()->create();

    expect((new AppPolicy)->createVariable($user, $appToCreateVariablesFor))->toBeFalse();

    $appToCreateVariablesFor->collaborators()->attach($user);

    expect((new AppPolicy)->createVariable($user, $appToCreateVariablesFor))->toBeFalse();
});

test('admin or owner can delete app', function () {
    $appToDelete = App::factory()->create();

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new AppPolicy)->delete($user, $appToDelete))->toBeTrue();

    $user->role = 'owner';

    expect((new AppPolicy)->delete($user, $appToDelete))->toBeTrue();
});

test('not admin or owner cant delete app', function () {
    $appToDelete = App::factory()->create();

    $user = User::factory()->create();

    expect((new AppPolicy)->delete($user, $appToDelete))->toBeFalse();
});

test('owner can force delete app', function () {
    $appToForceDelete = App::factory()->create();

    $user = User::factory()->state([
        'role' => 'owner',
    ])->create();

    expect((new AppPolicy)->forceDelete($user, $appToForceDelete))->toBeTrue();
});

test('not owner cant force delete app', function () {
    $appToForceDelete = App::factory()->create();

    $user = User::factory()->create();

    expect((new AppPolicy)->forceDelete($user, $appToForceDelete))->toBeFalse();

    $user->role = 'admin';

    expect((new AppPolicy)->forceDelete($user, $appToForceDelete))->toBeFalse();
});

test('admin or owner can restore app', function () {
    $appToRestore = App::factory()->create();

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new AppPolicy)->restore($user, $appToRestore))->toBeTrue();

    $user->role = 'owner';

    expect((new AppPolicy)->restore($user, $appToRestore))->toBeTrue();
});

test('not admin or owner cant restore app', function () {
    $appToRestore = App::factory()->create();

    $user = User::factory()->create();

    expect((new AppPolicy)->restore($user, $appToRestore))->toBeFalse();
});

test('admin or owner can update app', function () {
    $appToUpdate = App::factory()->create();

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new AppPolicy)->update($user, $appToUpdate))->toBeTrue();

    $user->role = 'owner';

    expect((new AppPolicy)->update($user, $appToUpdate))->toBeTrue();
});

test('not admin or owner cant update app', function () {
    $appToUpdate = App::factory()->create();

    $user = User::factory()->create();

    expect((new AppPolicy)->update($user, $appToUpdate))->toBeFalse();
});

test('admin or owner can view app', function () {
    $appToView = App::factory()->create();

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new AppPolicy)->view($user, $appToView))->toBeTrue();

    $user->role = 'owner';

    expect((new AppPolicy)->view($user, $appToView))->toBeTrue();
});

test('app collaborator can view app', function () {
    $appToView = App::factory()->create();

    $user = User::factory()->create();

    $appToView->collaborators()->attach($user);

    expect((new AppPolicy)->view($user, $appToView))->toBeTrue();
});

test('not admin or owner or app collaborator cant view app', function () {
    $appToView = App::factory()->create();

    $user = User::factory()->create();

    expect((new AppPolicy)->view($user, $appToView))->toBeFalse();
});

test('admin or owner can view all app', function () {
    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new AppPolicy)->viewAll($user))->toBeTrue();

    $user->role = 'owner';

    expect((new AppPolicy)->viewAll($user))->toBeTrue();
});

test('not admin or owner cant view all apps', function () {
    $user = User::factory()->create();

    expect((new AppPolicy)->viewAll($user))->toBeFalse();
});
