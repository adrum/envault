<?php

use App\Models\App;
use App\Models\Environment;
use App\Models\EnvironmentType;

test('creating an app auto-creates a default environment', function () {
    $app = App::factory()->create();

    expect(Environment::where('app_id', $app->id)->count())->toBe(1);
});

test('auto-created environment reuses the first existing type by sort_order', function () {
    $first = EnvironmentType::create(['name' => 'Production', 'color' => 'red', 'sort_order' => 0]);
    EnvironmentType::create(['name' => 'Staging', 'color' => 'blue', 'sort_order' => 1]);

    $app = App::factory()->create();

    $env = Environment::where('app_id', $app->id)->first();

    expect($env->environment_type_id)->toBe($first->id);
    expect($env->label)->toBe('Production');
});
