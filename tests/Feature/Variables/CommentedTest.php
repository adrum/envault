<?php

use App\Models\App;
use App\Models\User;
use App\Models\Variable;
use Laravel\Sanctum\Sanctum;
use App\Models\AppSetupToken;

beforeEach(function () {
    $this->authenticatedUser = User::factory()->state(['role' => 'owner'])->create();
    $this->actingAs($this->authenticatedUser);

    $this->app_ = App::factory()->create();
    $this->environmentId = $this->app_->environments()->value('environments.id');
});

function createVariable(App $app, int $environmentId, string $key, string $value, bool $commented = false): Variable
{
    $variable = $app->variables()->create([
        'key' => $key,
        'environment_id' => $environmentId,
        'is_commented' => $commented,
    ]);
    $variable->versions()->create(['value' => $value]);

    return $variable;
}

function storedLines(App $app): array
{
    return $app->variables()->get()
        ->map(fn (Variable $variable): string => $variable->key === ''
            ? '# ' . $variable->latest_version->value
            : ($variable->is_commented ? '# ' : '') . $variable->key . '=' . $variable->latest_version->value)
        ->all();
}

test('import stores commented-out lines, including ones that repeat a key', function () {
    $this->post(route('variables.import', $this->app_), [
        'env_content' => "DB_HOST=live\n# DB_HOST=staging\n#DB_HOST=local\n# just a note",
        'environment_id' => $this->environmentId,
    ])->assertSessionHasNoErrors();

    expect(storedLines($this->app_))->toBe(['DB_HOST=live', '# DB_HOST=staging', '# DB_HOST=local', '# just a note']);
});

test('when import content sets a key twice the last uncommented line wins', function () {
    $this->post(route('variables.import', $this->app_), [
        'env_content' => "DB_HOST=one\nDB_PORT=3306\nDB_HOST=two",
        'environment_id' => $this->environmentId,
    ])->assertSessionHasNoErrors();

    expect(storedLines($this->app_))->toBe(['DB_PORT=3306', 'DB_HOST=two']);
});

test('import keeps plain comments in place and they survive a re-import', function () {
    $content = "# Database\nDB_HOST=live\n# Mail\nMAIL_HOST=smtp";

    $this->post(route('variables.import', $this->app_), [
        'env_content' => $content,
        'environment_id' => $this->environmentId,
    ])->assertSessionHasNoErrors();
    $ids = $this->app_->variables()->pluck('id')->all();

    $this->post(route('variables.import', $this->app_), [
        'env_content' => $content,
        'environment_id' => $this->environmentId,
    ])->assertSessionHasNoErrors();

    expect(storedLines($this->app_))->toBe(['# Database', 'DB_HOST=live', '# Mail', 'MAIL_HOST=smtp'])
        ->and($this->app_->variables()->pluck('id')->all())->toBe($ids);
});

test('export writes plain comments back as comment lines', function () {
    createVariable($this->app_, $this->environmentId, '', 'Database', commented: true);
    createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');

    $exported = $this->get(route('variables.export', ['app' => $this->app_, 'environment' => $this->environmentId]))
        ->getContent();

    expect($exported)->toBe("# Database\nDB_HOST=live");
});

test('a plain comment cannot be uncommented', function () {
    $comment = createVariable($this->app_, $this->environmentId, '', 'Database', commented: true);

    $this->patch(route('variables.update', $comment), ['is_commented' => false])
        ->assertSessionHasErrors('key');

    expect($comment->fresh()->is_commented)->toBeTrue();
});

test('commenting out a line on import keeps the variable and its history', function () {
    $variable = createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');

    $this->post(route('variables.import', $this->app_), [
        'env_content' => '# DB_HOST=live',
        'environment_id' => $this->environmentId,
    ])->assertSessionHasNoErrors();

    expect($variable->fresh()->is_commented)->toBeTrue()
        ->and($variable->versions()->count())->toBe(1)
        ->and($this->app_->variables()->count())->toBe(1);
});

test('swapping the active and commented lines on import keeps both variables', function () {
    $live = createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');
    $local = createVariable($this->app_, $this->environmentId, 'DB_HOST', 'local', commented: true);

    $this->post(route('variables.import', $this->app_), [
        'env_content' => "# DB_HOST=live\nDB_HOST=local",
        'environment_id' => $this->environmentId,
    ])->assertSessionHasNoErrors();

    expect($live->fresh()->is_commented)->toBeTrue()
        ->and($local->fresh()->is_commented)->toBeFalse()
        ->and($live->versions()->count() + $local->versions()->count())->toBe(2);
});

test('import removes commented variables that are no longer in the content', function () {
    createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');
    $removed = createVariable($this->app_, $this->environmentId, 'DB_HOST', 'local', commented: true);

    $this->post(route('variables.import', $this->app_), [
        'env_content' => 'DB_HOST=live',
        'environment_id' => $this->environmentId,
    ])->assertSessionHasNoErrors();

    $this->assertSoftDeleted('variables', ['id' => $removed->id]);
});

test('export writes commented variables as comment lines', function () {
    createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');
    createVariable($this->app_, $this->environmentId, 'DB_HOST', "multi\nline", commented: true);

    $exported = $this->get(route('variables.export', ['app' => $this->app_, 'environment' => $this->environmentId]))
        ->getContent();

    expect($exported)->toBe("DB_HOST=live\n# DB_HOST=\"multi\\nline\"");
});

test('a new variable may reuse the key of a commented-out one', function () {
    createVariable($this->app_, $this->environmentId, 'DB_HOST', 'local', commented: true);

    $this->post(route('variables.store', $this->app_), [
        'key' => 'DB_HOST',
        'value' => 'live',
        'environment_id' => $this->environmentId,
    ])->assertSessionHasNoErrors();

    expect(storedLines($this->app_))->toEqualCanonicalizing(['# DB_HOST=local', 'DB_HOST=live']);
});

test('can comment out a variable', function () {
    $variable = createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');

    $this->patch(route('variables.update', $variable), ['is_commented' => true])
        ->assertSessionHasNoErrors();

    expect($variable->fresh()->is_commented)->toBeTrue();
});

test('cannot uncomment a variable while another active variable has its key', function () {
    createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');
    $commented = createVariable($this->app_, $this->environmentId, 'DB_HOST', 'local', commented: true);

    $this->patch(route('variables.update', $commented), ['is_commented' => false])
        ->assertSessionHasErrors('key');

    expect($commented->fresh()->is_commented)->toBeTrue();
});

test('a commented-out variable can be renamed to a key that is already in use', function () {
    createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');
    $commented = createVariable($this->app_, $this->environmentId, 'OLD_HOST', 'local', commented: true);

    $this->patch(route('variables.update', $commented), ['key' => 'DB_HOST'])
        ->assertSessionHasNoErrors();

    expect($commented->fresh()->key)->toBe('DB_HOST');
});

test('the CLI update endpoint leaves out commented variables', function () {
    createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');
    createVariable($this->app_, $this->environmentId, 'DB_PORT', '3306', commented: true);
    Sanctum::actingAs($this->authenticatedUser);

    $this->postJson("/api/v1/apps/{$this->app_->id}/update")
        ->assertOk()
        ->assertJsonCount(1, 'variables')
        ->assertJsonPath('variables.0.key', 'DB_HOST');
});

test('the CLI setup endpoint leaves out commented variables', function (bool $scopedToEnvironment) {
    createVariable($this->app_, $this->environmentId, 'DB_HOST', 'live');
    createVariable($this->app_, $this->environmentId, 'DB_PORT', '3306', commented: true);
    $setupToken = AppSetupToken::create([
        'app_id' => $this->app_->id,
        'user_id' => $this->authenticatedUser->id,
        'environment_id' => $scopedToEnvironment ? $this->environmentId : null,
        'token' => 'setup-token',
    ]);

    $this->postJson("/api/v1/apps/{$this->app_->id}/setup/{$setupToken->token}")
        ->assertOk()
        ->assertJsonCount(1, 'app.variables')
        ->assertJsonPath('app.variables.0.key', 'DB_HOST');
})->with([
    'environment token' => true,
    'app-wide token' => false,
]);
