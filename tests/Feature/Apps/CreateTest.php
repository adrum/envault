<?php

use App\Models\App;
use App\Models\User;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can create app', function () {
    $appToCreate = App::factory()->make();

    $this->post(route('apps.store'), [
        'name' => $appToCreate->name,
    ])->assertRedirect();

    $this->assertDatabaseHas('apps', [
        'name' => $appToCreate->name,
    ]);
});

test('name is required', function () {
    $this->post(route('apps.store'))->assertSessionHasErrors('name');
});
