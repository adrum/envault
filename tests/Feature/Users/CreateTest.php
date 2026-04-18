<?php

use App\Models\User;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can create user', function () {
    $userToCreate = User::factory()->make();

    $this->post(route('users.store'), [
        'email' => $userToCreate->email,
        'first_name' => $userToCreate->first_name,
        'last_name' => $userToCreate->last_name,
    ])->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => $userToCreate->email,
        'first_name' => $userToCreate->first_name,
        'last_name' => $userToCreate->last_name,
    ]);
});

test('email is email', function () {
    $this->post(route('users.store'), [
        'email' => 'email',
        'first_name' => 'First',
        'last_name' => 'Last',
    ])->assertSessionHasErrors('email');
});

test('email is required', function () {
    $this->post(route('users.store'))->assertSessionHasErrors('email');
});

test('email is unique', function () {
    $this->post(route('users.store'), [
        'email' => $this->authenticatedUser->email,
        'first_name' => 'First',
        'last_name' => 'Last',
    ])->assertSessionHasErrors('email');
});

test('first name is required', function () {
    $this->post(route('users.store'))->assertSessionHasErrors('first_name');
});

test('last name is required', function () {
    $this->post(route('users.store'))->assertSessionHasErrors('last_name');
});
