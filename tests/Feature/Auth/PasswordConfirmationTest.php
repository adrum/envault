<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/confirm-password'),
    );
});

test('password confirmation requires authentication', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});

test('user with password can confirm with correct password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('password.confirm'), [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('user with password cannot confirm with incorrect password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('password.confirm'), [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});

test('sso user without password can confirm by typing confirm', function () {
    $user = User::factory()->create(['password' => null]);

    $response = $this->actingAs($user)->post(route('password.confirm'), [
        'password' => 'confirm',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('sso user without password cannot confirm with wrong input', function () {
    $user = User::factory()->create(['password' => null]);

    $response = $this->actingAs($user)->post(route('password.confirm'), [
        'password' => 'wrong-input',
    ]);

    $response->assertSessionHasErrors();
});
