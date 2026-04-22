<?php

use App\Models\App;
use App\Models\User;
use App\Models\Environment;
use App\Models\EnvironmentType;

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

test('creating an app only creates one default environment', function () {
    EnvironmentType::create(['name' => 'Production', 'color' => 'red', 'sort_order' => 0]);

    $this->post(route('apps.store'), [
        'name' => 'My New App',
    ])->assertRedirect();

    $app = App::where('name', 'My New App')->firstOrFail();

    expect(Environment::where('app_id', $app->id)->count())->toBe(1);
});
