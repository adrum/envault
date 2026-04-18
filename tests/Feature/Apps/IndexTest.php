<?php

use App\Models\App;
use App\Models\User;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can view apps', function () {
    App::factory()->create();

    $this->get(route('apps.index'))->assertOk();
});
