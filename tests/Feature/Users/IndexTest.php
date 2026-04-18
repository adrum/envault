<?php

use App\Models\User;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can view users', function () {
    $this->get(route('users.index'))->assertOk();
});
