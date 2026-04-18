<?php

use App\Models\User;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can update user', function () {
    $userToUpdate = User::factory()->create();

    $this->patch(route('users.update', $userToUpdate), [
        'email' => $userToUpdate->email,
        'first_name' => $userToUpdate->first_name,
        'last_name' => $userToUpdate->last_name,
        'role' => 'admin',
    ])->assertRedirect();

    $this->assertDatabaseHas('users', [
        'id' => $userToUpdate->id,
        'email' => $userToUpdate->email,
        'first_name' => $userToUpdate->first_name,
        'last_name' => $userToUpdate->last_name,
        'role' => 'admin',
    ]);
});

test('email is email', function () {
    $userToUpdate = User::factory()->create();

    $this->patch(route('users.update', $userToUpdate), [
        'email' => 'email',
        'first_name' => $userToUpdate->first_name,
        'last_name' => $userToUpdate->last_name,
    ])->assertSessionHasErrors('email');
});

test('email is required', function () {
    $userToUpdate = User::factory()->create();

    $this->patch(route('users.update', $userToUpdate), [
        'email' => '',
        'first_name' => $userToUpdate->first_name,
        'last_name' => $userToUpdate->last_name,
    ])->assertSessionHasErrors('email');
});

test('email is unique', function () {
    $userToUpdate = User::factory()->create();

    $this->patch(route('users.update', $userToUpdate), [
        'email' => $this->authenticatedUser->email,
        'first_name' => $userToUpdate->first_name,
        'last_name' => $userToUpdate->last_name,
    ])->assertSessionHasErrors('email');
});

test('first name is required', function () {
    $userToUpdate = User::factory()->create();

    $this->patch(route('users.update', $userToUpdate), [
        'email' => $userToUpdate->email,
        'first_name' => '',
        'last_name' => $userToUpdate->last_name,
    ])->assertSessionHasErrors('first_name');
});

test('last name is required', function () {
    $userToUpdate = User::factory()->create();

    $this->patch(route('users.update', $userToUpdate), [
        'email' => $userToUpdate->email,
        'first_name' => $userToUpdate->first_name,
        'last_name' => '',
    ])->assertSessionHasErrors('last_name');
});
