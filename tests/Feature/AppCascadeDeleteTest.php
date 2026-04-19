<?php

use App\Models\App;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\EnvironmentType;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($this->user);
});

it('cascades soft delete from app to environments and variables', function () {
    $app = App::create(['name' => 'Test App']);

    $type = EnvironmentType::firstOrCreate(
        ['name' => 'Default'],
        ['color' => 'gray', 'sort_order' => 0]
    );

    $env = $app->environments()->create([
        'environment_type_id' => $type->id,
        'label' => 'Default',
    ]);

    $variable = $env->variables()->create([
        'app_id' => $app->id,
        'key' => 'TEST_VAR',
        'sort_order' => 0,
    ]);

    $app->delete();

    expect($app->trashed())->toBeTrue();
    expect($env->fresh()->trashed())->toBeTrue();
    expect($variable->fresh()->trashed())->toBeTrue();
});

it('cascades restore from app to environments and variables', function () {
    $app = App::create(['name' => 'Test App']);

    $type = EnvironmentType::firstOrCreate(
        ['name' => 'Default'],
        ['color' => 'gray', 'sort_order' => 0]
    );

    $env = $app->environments()->create([
        'environment_type_id' => $type->id,
        'label' => 'Default',
    ]);

    $variable = $env->variables()->create([
        'app_id' => $app->id,
        'key' => 'TEST_VAR',
        'sort_order' => 0,
    ]);

    $app->delete();

    expect($app->trashed())->toBeTrue();
    expect($env->fresh()->trashed())->toBeTrue();
    expect($variable->fresh()->trashed())->toBeTrue();

    $app->restore();

    expect($app->trashed())->toBeFalse();
    expect($env->fresh()->trashed())->toBeFalse();
    expect($variable->fresh()->trashed())->toBeFalse();
});
