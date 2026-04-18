<?php

use App\Models\User;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can delete user', function () {
    $userToDelete = User::factory()->create();

    $this->delete(route('users.destroy', $userToDelete))->assertRedirect();

    $this->assertSoftDeleted('users', [
        'id' => $userToDelete->id,
    ]);
});
