<?php

use App\Models\App;
use App\Models\User;
use App\Models\Variable;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can delete variable', function () {
    $app = App::factory()->create();
    $variableToDelete = $app->variables()->create(Variable::factory()->make()->toArray());

    $this->delete(route('variables.destroy', $variableToDelete))->assertRedirect();

    $this->assertSoftDeleted('variables', [
        'id' => $variableToDelete->id,
    ]);
});
