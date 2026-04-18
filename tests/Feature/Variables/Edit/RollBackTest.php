<?php

use App\Models\App;
use App\Models\User;
use App\Models\Variable;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can roll back to a previous version', function () {
    $app = App::factory()->create();
    $variable = $app->variables()->create(Variable::factory()->make()->toArray());
    $oldVersion = $variable->versions()->create(['value' => 'old', 'user_id' => $this->authenticatedUser->id]);
    $variable->versions()->create(['value' => 'new', 'user_id' => $this->authenticatedUser->id]);

    $this->post(route('variables.rollback', $variable), [
        'version_id' => $oldVersion->id,
    ])->assertRedirect();

    expect($variable->fresh()->latest_version->value)->toBe('old');
});

test('version_id is required', function () {
    $app = App::factory()->create();
    $variable = $app->variables()->create(Variable::factory()->make()->toArray());

    $this->post(route('variables.rollback', $variable))->assertSessionHasErrors('version_id');
});
