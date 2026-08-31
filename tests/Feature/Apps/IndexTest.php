<?php

use App\Models\App;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);
});

test('can view apps', function () {
    App::factory()->create();

    $this->get(route('apps.index'))->assertOk();
});

test('sorts apps by name ascending by default', function () {
    App::factory()->create(['name' => 'Charlie']);
    App::factory()->create(['name' => 'Alpha']);
    App::factory()->create(['name' => 'Bravo']);

    $this->get(route('apps.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'name')
            ->where('direction', 'asc')
            ->where('apps.data.0.name', 'Alpha')
            ->where('apps.data.1.name', 'Bravo')
            ->where('apps.data.2.name', 'Charlie'));
});

test('sorts apps by name descending', function () {
    App::factory()->create(['name' => 'Alpha']);
    App::factory()->create(['name' => 'Charlie']);
    App::factory()->create(['name' => 'Bravo']);

    $this->get(route('apps.index', ['sort' => 'name', 'direction' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('direction', 'desc')
            ->where('apps.data.0.name', 'Charlie')
            ->where('apps.data.1.name', 'Bravo')
            ->where('apps.data.2.name', 'Alpha'));
});

test('sorts apps by date created', function () {
    $older = App::factory()->create(['created_at' => now()->subDays(2)]);
    $newer = App::factory()->create(['created_at' => now()]);

    $this->get(route('apps.index', ['sort' => 'created_at', 'direction' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('apps.data.0.id', $newer->id)
            ->where('apps.data.1.id', $older->id));
});

test('falls back to name sort for an invalid sort column', function () {
    App::factory()->create(['name' => 'Bravo']);
    App::factory()->create(['name' => 'Alpha']);

    $this->get(route('apps.index', ['sort' => 'id; drop table apps']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'name')
            ->where('apps.data.0.name', 'Alpha'));
});
