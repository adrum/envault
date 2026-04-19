<?php

use App\Models\App;
use App\Models\User;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can delete app', function () {
    $appToDelete = App::factory()->create();

    $this->delete(route('apps.destroy', $appToDelete), [
        'confirm_name' => $appToDelete->name,
    ])->assertRedirect();

    $this->assertSoftDeleted('apps', [
        'id' => $appToDelete->id,
    ]);
});

test('can update details', function () {
    $appToUpdate = App::factory()->create();
    $newDetails = App::factory()->make();

    $this->patch(route('apps.update', $appToUpdate), [
        'name' => $newDetails->name,
    ])->assertRedirect();

    $this->assertDatabaseHas('apps', [
        'id' => $appToUpdate->id,
        'name' => $newDetails->name,
    ]);
});

test('name is required', function () {
    $appToUpdate = App::factory()->create();

    $this->patch(route('apps.update', $appToUpdate), [
        'name' => null,
    ])->assertSessionHasErrors('name');
});
