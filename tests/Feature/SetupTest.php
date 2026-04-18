<?php

use App\Models\User;

test('can setup', function () {
    $userToCreate = User::factory()->make();

    $this->post(route('setup'), [
        'email' => $userToCreate->email,
        'first_name' => $userToCreate->first_name,
        'last_name' => $userToCreate->last_name,
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ])->assertRedirect(route('apps.index'));

    $this->assertDatabaseHas('users', [
        'email' => $userToCreate->email,
        'first_name' => $userToCreate->first_name,
        'last_name' => $userToCreate->last_name,
        'role' => 'owner',
    ]);

    $this->assertAuthenticatedAs(User::first());
});

test('cannot setup when users present', function () {
    User::factory()->create();

    $this->get(route('setup'))->assertRedirect('/');
});

test('email is email', function () {
    $this->post(route('setup'), [
        'email' => 'email',
        'first_name' => 'First',
        'last_name' => 'Last',
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ])->assertSessionHasErrors('email');
});

test('email is required', function () {
    $this->post(route('setup'))->assertSessionHasErrors('email');
});

test('first name is required', function () {
    $this->post(route('setup'))->assertSessionHasErrors('first_name');
});

test('last name is required', function () {
    $this->post(route('setup'))->assertSessionHasErrors('last_name');
});
