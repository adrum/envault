<?php

use App\Models\App;
use App\Models\User;
use App\Enums\UserRole;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($this->user);
});

it('auto-generates a slug from app name on creation', function () {
    $app = App::create(['name' => 'My Awesome App']);

    expect($app->slug)->toBe('my-awesome-app');
});

it('generates unique slugs for duplicate names', function () {
    App::create(['name' => 'Test App']);
    $app2 = App::create(['name' => 'Test App']);

    expect($app2->slug)->toBe('test-app-1');
});

it('does not change slug when name is updated', function () {
    $app = App::create(['name' => 'Original Name']);
    $originalSlug = $app->slug;

    $app->update(['name' => 'New Name']);

    expect($app->fresh()->slug)->toBe($originalSlug);
});
