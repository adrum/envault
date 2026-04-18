<?php

use App\Models\App;
use App\Models\User;

test('full name determined', function () {
    $user = User::factory()->create();

    expect($user->first_name . ' ' . $user->last_name)->toEqual($user->full_name);
});

test('admin or owner status determined for admin', function () {
    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect($user->isAdminOrOwner())->toBeTrue();
});

test('admin or owner status determined for owner', function () {
    $user = User::factory()->state([
        'role' => 'owner',
    ])->create();

    expect($user->isAdminOrOwner())->toBeTrue();
});

test('admin or owner status determined for not admin or owner', function () {
    $user = User::factory()->create();

    expect($user->isAdminOrOwner())->toBeFalse();
});

test('app admin status determined for app admin', function () {
    $user = User::factory()->create();

    $app = App::factory()->create();

    $app->collaborators()->attach($user, [
        'role' => 'admin',
    ]);

    expect($user->isAppAdmin($app))->toBeTrue();
});

test('app admin status determined for not app admin', function () {
    $user = User::factory()->create();

    $app = App::factory()->create();

    expect($user->isAppAdmin($app))->toBeFalse();

    $app->collaborators()->attach($user);

    expect($user->isAppAdmin($app))->toBeFalse();
});

test('app collabortator status determined for app collabortator', function () {
    $user = User::factory()->create();

    $app = App::factory()->create();

    $app->collaborators()->attach($user);

    expect($user->isAppCollaborator($app))->toBeTrue();
});

test('app collabortator status determined for not app collabortator', function () {
    $user = User::factory()->create();

    $app = App::factory()->create();

    expect($user->isAppCollaborator($app))->toBeFalse();
});

test('owner status determined for owner', function () {
    $user = User::factory()->state([
        'role' => 'owner',
    ])->create();

    expect($user->isOwner())->toBeTrue();
});

test('owner status determined for not owner', function () {
    $user = User::factory()->create();

    expect($user->isOwner())->toBeFalse();
});
