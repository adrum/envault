<?php

use App\Models\App;
use App\Models\User;
use App\Models\Environment;
use App\Models\EnvironmentType;

beforeEach(function () {
    $this->user = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->user);
});

test('can create a new environment from a type', function () {
    $app = App::factory()->create();
    $type = EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);

    $this->post(route('environments.store', $app), [
        'environment_type_id' => $type->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('environments', [
        'app_id' => $app->id,
        'environment_type_id' => $type->id,
        'label' => 'Staging',
    ]);
});

test('custom_label is accepted when type has no per-app limit of 1', function () {
    $app = App::factory()->create();
    $type = EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);

    $this->post(route('environments.store', $app), [
        'environment_type_id' => $type->id,
        'custom_label' => 'EU-West',
    ])->assertRedirect();

    $this->assertDatabaseHas('environments', [
        'app_id' => $app->id,
        'environment_type_id' => $type->id,
        'custom_label' => 'EU-West',
    ]);
});

test('custom_label is ignored when type has per_app_limit of 1', function () {
    $app = App::factory()->create();
    $type = EnvironmentType::create(['name' => 'Production', 'color' => 'red', 'per_app_limit' => 1, 'sort_order' => 2]);

    $this->post(route('environments.store', $app), [
        'environment_type_id' => $type->id,
        'custom_label' => 'Should not stick',
    ])->assertRedirect();

    $this->assertDatabaseHas('environments', [
        'app_id' => $app->id,
        'environment_type_id' => $type->id,
        'custom_label' => null,
    ]);
});

test('per_app_limit blocks creation beyond the limit', function () {
    $app = App::factory()->create();
    $type = EnvironmentType::create(['name' => 'Production', 'color' => 'red', 'per_app_limit' => 1, 'sort_order' => 2]);

    $app->environments()->create([
        'environment_type_id' => $type->id,
        'label' => $type->name,
        'color' => $type->color,
    ]);

    $this->post(route('environments.store', $app), [
        'environment_type_id' => $type->id,
    ])->assertSessionHasErrors('environment_type_id');

    expect(Environment::where('app_id', $app->id)->where('environment_type_id', $type->id)->count())->toBe(1);
});

test('environment_type_id must exist', function () {
    $app = App::factory()->create();

    $this->post(route('environments.store', $app), [
        'environment_type_id' => 999999,
    ])->assertSessionHasErrors('environment_type_id');
});

test('non-admin cannot create environments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $app = App::factory()->create();
    $type = EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);

    $this->post(route('environments.store', $app), [
        'environment_type_id' => $type->id,
    ])->assertForbidden();
});

test('can reclassify environment to a different type', function () {
    $app = App::factory()->create();
    $staging = EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);
    $qa = EnvironmentType::create(['name' => 'QA', 'color' => 'yellow', 'sort_order' => 2]);

    $env = $app->environments()->create([
        'environment_type_id' => $staging->id,
        'label' => $staging->name,
        'color' => $staging->color,
    ]);

    $this->patch(route('environments.update', $env), [
        'environment_type_id' => $qa->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('environments', [
        'id' => $env->id,
        'environment_type_id' => $qa->id,
        'label' => 'QA',
    ]);
});

test('reclassify is blocked by per_app_limit', function () {
    $app = App::factory()->create();
    $staging = EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);
    $prod = EnvironmentType::create(['name' => 'Production', 'color' => 'red', 'per_app_limit' => 1, 'sort_order' => 2]);

    $app->environments()->create([
        'environment_type_id' => $prod->id,
        'label' => $prod->name,
        'color' => $prod->color,
    ]);
    $stagingEnv = $app->environments()->create([
        'environment_type_id' => $staging->id,
        'label' => $staging->name,
        'color' => $staging->color,
    ]);

    $this->patch(route('environments.update', $stagingEnv), [
        'environment_type_id' => $prod->id,
    ])->assertSessionHasErrors('environment_type_id');
});

test('can update custom_label', function () {
    $app = App::factory()->create();
    $type = EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);

    $env = $app->environments()->create([
        'environment_type_id' => $type->id,
        'label' => $type->name,
        'color' => $type->color,
    ]);

    $this->patch(route('environments.update', $env), [
        'custom_label' => 'EU',
    ])->assertRedirect();

    expect($env->fresh()->custom_label)->toBe('EU');
});

test('can destroy environment with matching confirm_label', function () {
    $app = App::factory()->create();
    $type = EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);

    $env = $app->environments()->create([
        'environment_type_id' => $type->id,
        'label' => $type->name,
        'color' => $type->color,
    ]);

    $this->delete(route('environments.destroy', $env), [
        'confirm_label' => $env->label,
    ])->assertRedirect();

    $this->assertSoftDeleted('environments', ['id' => $env->id]);
});

test('cannot destroy last environment on an app', function () {
    $app = App::factory()->create();
    $env = Environment::where('app_id', $app->id)->first();

    $this->delete(route('environments.destroy', $env), [
        'confirm_label' => $env->label,
    ])->assertSessionHasErrors('environment');

    expect($env->fresh()->trashed())->toBeFalse();
});

test('destroy requires matching confirm_label', function () {
    $app = App::factory()->create();
    $type = EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);

    $env = $app->environments()->create([
        'environment_type_id' => $type->id,
        'label' => $type->name,
        'color' => $type->color,
    ]);

    $this->delete(route('environments.destroy', $env), [
        'confirm_label' => 'wrong',
    ])->assertSessionHasErrors('confirm_label');

    expect($env->fresh()->trashed())->toBeFalse();
});
