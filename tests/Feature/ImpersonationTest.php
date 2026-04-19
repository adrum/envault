<?php

use App\Models\User;
use App\Enums\UserRole;

it('allows owners to impersonate other users', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $user = User::factory()->create(['role' => UserRole::USER]);

    $this->actingAs($owner)
        ->post("/impersonate/{$user->id}")
        ->assertRedirect('/apps');

    expect(auth()->id())->toBe($user->id);
    expect(session('impersonator_id'))->toBe($owner->id);
});

it('prevents non-owners from impersonating', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $user = User::factory()->create(['role' => UserRole::USER]);

    $this->actingAs($admin)
        ->post("/impersonate/{$user->id}")
        ->assertForbidden();
});

it('stops impersonation and restores original user', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $user = User::factory()->create(['role' => UserRole::USER]);

    $this->actingAs($owner)
        ->post("/impersonate/{$user->id}");

    $this->post('/impersonate-stop')
        ->assertRedirect('/apps');

    expect(auth()->id())->toBe($owner->id);
    expect(session('impersonator_id'))->toBeNull();
});

it('prevents impersonating yourself', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->post("/impersonate/{$owner->id}")
        ->assertRedirect();

    expect(session('impersonator_id'))->toBeNull();
});
