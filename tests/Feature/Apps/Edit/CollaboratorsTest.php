<?php

use App\Models\App;
use App\Models\User;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can add user as collaborator', function () {
    $app = App::factory()->create();
    $userToAdd = User::factory()->create();

    $this->post(route('apps.collaborators.add', $app), [
        'user_id' => $userToAdd->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('app_collaborators', [
        'app_id' => $app->id,
        'user_id' => $userToAdd->id,
    ]);
});

test('can remove user from collaborator', function () {
    $app = App::factory()->create();
    $userToRemove = User::factory()->create();
    $app->collaborators()->attach($userToRemove);

    $this->delete(route('apps.collaborators.remove', $app), [
        'user_id' => $userToRemove->id,
    ])->assertRedirect();

    $this->assertDatabaseMissing('app_collaborators', [
        'app_id' => $app->id,
        'user_id' => $userToRemove->id,
    ]);
});

test('can update collaborator role', function () {
    $app = App::factory()->create();
    $collaboratorToUpdate = User::factory()->create();
    $app->collaborators()->attach($collaboratorToUpdate);

    $this->patch(route('apps.collaborators.update-role', $app), [
        'user_id' => $collaboratorToUpdate->id,
        'role' => 'admin',
    ])->assertRedirect();

    $this->assertDatabaseHas('app_collaborators', [
        'app_id' => $app->id,
        'user_id' => $collaboratorToUpdate->id,
        'role' => 'admin',
    ]);
});

test('user to add exists', function () {
    $app = App::factory()->create();

    $this->post(route('apps.collaborators.add', $app), [
        'user_id' => 9999,
    ])->assertSessionHasErrors('user_id');
});

test('user to add id is required', function () {
    $app = App::factory()->create();

    $this->post(route('apps.collaborators.add', $app))->assertSessionHasErrors('user_id');
});
