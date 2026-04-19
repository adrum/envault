<?php

use App\Models\User;
use App\Enums\UserRole;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => UserRole::ADMIN]);
    $this->actingAs($this->user);
});

it('lists user tokens', function () {
    $this->user->createToken('test-token');

    $this->get('/settings/tokens')
        ->assertOk();
});

it('creates a new token', function () {
    $this->post('/settings/tokens', ['name' => 'my-token'])
        ->assertRedirect();

    expect($this->user->tokens()->count())->toBe(1);
    expect($this->user->tokens()->first()->name)->toBe('my-token');
});

it('deletes a token', function () {
    $token = $this->user->createToken('delete-me');

    $this->delete("/settings/tokens/{$token->accessToken->id}")
        ->assertRedirect();

    expect($this->user->tokens()->count())->toBe(0);
});

it('requires a name to create a token', function () {
    $this->post('/settings/tokens', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('returns the plaintext token once via flash data', function () {
    $this->post('/settings/tokens', ['name' => 'flashed'])
        ->assertRedirect()
        ->assertSessionHas('newToken', fn ($value) => is_string($value) && str_contains($value, '|'));
});

it('can rename a token', function () {
    $token = $this->user->createToken('old-name');

    $this->patch("/settings/tokens/{$token->accessToken->id}", ['name' => 'new-name'])
        ->assertRedirect();

    expect($this->user->tokens()->first()->name)->toBe('new-name');
});

it('requires a name to rename a token', function () {
    $token = $this->user->createToken('some-name');

    $this->patch("/settings/tokens/{$token->accessToken->id}", ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('cannot rename another user\'s token', function () {
    $otherUser = User::factory()->create();
    $otherToken = $otherUser->createToken('not-yours');

    $this->patch("/settings/tokens/{$otherToken->accessToken->id}", ['name' => 'hijacked'])
        ->assertRedirect();

    expect($otherUser->tokens()->first()->name)->toBe('not-yours');
});

it('cannot delete another user\'s token', function () {
    $otherUser = User::factory()->create();
    $otherToken = $otherUser->createToken('not-yours');

    $this->delete("/settings/tokens/{$otherToken->accessToken->id}")
        ->assertRedirect();

    expect($otherUser->tokens()->count())->toBe(1);
});

it('requires authentication to list tokens', function () {
    auth()->logout();

    $this->get('/settings/tokens')->assertRedirect('/login');
});

it('requires authentication to create tokens', function () {
    auth()->logout();

    $this->post('/settings/tokens', ['name' => 'anon'])->assertRedirect('/login');
});
