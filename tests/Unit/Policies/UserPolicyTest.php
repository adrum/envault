<?php

use App\Models\User;
use App\Policies\UserPolicy;

test('admin or owner can create user', function () {
    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new UserPolicy)->create($user))->toBeTrue();

    $user->role = 'owner';

    expect((new UserPolicy)->create($user))->toBeTrue();
});

test('not admin or owner cant create user', function () {
    $user = User::factory()->create();

    expect((new UserPolicy)->create($user))->toBeFalse();
});

test('owner can delete user', function () {
    $userToDelete = User::factory()->create();

    $user = User::factory()->state([
        'role' => 'owner',
    ])->create();

    expect((new UserPolicy)->delete($user, $userToDelete))->toBeTrue();
});

test('owner cant delete themselves', function () {
    $user = User::factory()->state([
        'role' => 'owner',
    ])->create();

    expect((new UserPolicy)->delete($user, $user))->toBeFalse();
});

test('admin or owner can delete user if not admin or owner', function () {
    $userToDelete = User::factory()->create();

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new UserPolicy)->delete($user, $userToDelete))->toBeTrue();

    $user->role = 'owner';

    expect((new UserPolicy)->delete($user, $userToDelete))->toBeTrue();
});

test('not admin or owner cant delete users', function () {
    $userToDelete = User::factory()->create();

    $user = User::factory()->create();

    expect((new UserPolicy)->delete($user, $userToDelete))->toBeFalse();
});

test('owner can update user', function () {
    $userToUpdate = User::factory()->create();

    $user = User::factory()->state([
        'role' => 'owner',
    ])->create();

    expect((new UserPolicy)->update($user, $userToUpdate))->toBeTrue();
});

test('admin or owner can update user if not admin or owner', function () {
    $userToUpdate = User::factory()->create();

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new UserPolicy)->update($user, $userToUpdate))->toBeTrue();

    $user->role = 'owner';

    expect((new UserPolicy)->update($user, $userToUpdate))->toBeTrue();
});

test('not admin or owner cant update users', function () {
    $userToUpdate = User::factory()->create();

    $user = User::factory()->create();

    expect((new UserPolicy)->update($user, $userToUpdate))->toBeFalse();
});

test('owner can update user role', function () {
    $userToUpdate = User::factory()->create();

    $user = User::factory()->state([
        'role' => 'owner',
    ])->create();

    expect((new UserPolicy)->updateRole($user, $userToUpdate))->toBeTrue();
});

test('owner cant update their own role', function () {
    $user = User::factory()->state([
        'role' => 'owner',
    ])->create();

    expect((new UserPolicy)->updateRole($user, $user))->toBeFalse();
});

test('not owner cant update user role', function () {
    $userToUpdate = User::factory()->create();

    $user = User::factory()->create();

    expect((new UserPolicy)->updateRole($user, $userToUpdate))->toBeFalse();

    $user->role = 'admin';

    expect((new UserPolicy)->updateRole($user, $userToUpdate))->toBeFalse();
});

test('admin or owner can view user', function () {
    $userToView = User::factory()->create();

    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new UserPolicy)->view($user, $userToView))->toBeTrue();

    $user->role = 'owner';

    expect((new UserPolicy)->view($user, $userToView))->toBeTrue();
});

test('not admin or owner cant view user', function () {
    $userToView = User::factory()->create();

    $user = User::factory()->create();

    expect((new UserPolicy)->view($user, $userToView))->toBeFalse();
});

test('admin or owner can view any users', function () {
    $user = User::factory()->state([
        'role' => 'admin',
    ])->create();

    expect((new UserPolicy)->viewAny($user))->toBeTrue();

    $user->role = 'owner';

    expect((new UserPolicy)->viewAny($user))->toBeTrue();
});

test('not admin or owner cant view any users', function () {
    $user = User::factory()->create();

    expect((new UserPolicy)->viewAny($user))->toBeFalse();
});
