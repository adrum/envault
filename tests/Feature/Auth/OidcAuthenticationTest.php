<?php

use App\Models\User;
use App\Enums\UserRole;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config([
        'oidc.driver_prefix' => 'oidc_',
        // Mirrored into services.* by OpenIDConnectServiceProvider at boot,
        // which has already run by the time a test overrides the connection.
        'services.oidc_sso' => [
            'base_url' => 'https://id.example.com',
            'client_id' => 'envault',
            'client_secret' => 'secret',
            'redirect' => '/login/oidc/sso/callback',
        ],
        'oidc.connections.sso' => [
            'base_url' => 'https://id.example.com',
            'client_id' => 'envault',
            'client_secret' => 'secret',
            'redirect' => '/login/oidc/sso/callback',
            'label' => 'Sign in with Example',
            'logo' => '/images/sso.svg',
        ],
    ]);
});

/**
 * @param  array<string, mixed>  $claims
 */
function fakeOidcUser(array $claims = []): void
{
    $claims = array_merge([
        'sub' => 'sub-123',
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'given_name' => 'Ada',
        'family_name' => 'Lovelace',
    ], $claims);

    $socialiteUser = (new SocialiteUser)->setRaw($claims)->map([
        'id' => $claims['sub'] ?? null,
        'email' => $claims['email'] ?? null,
        'name' => $claims['name'] ?? null,
    ]);

    $provider = Mockery::mock(Laravel\Socialite\Contracts\Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('oidc_sso')->andReturn($provider);
}

function fakePassportUser(string $email = 'ada@example.com'): void
{
    $socialiteUser = (new SocialiteUser)->map([
        'id' => 'passport-1',
        'email' => $email,
        'name' => 'Ada Lovelace',
    ]);

    $provider = Mockery::mock(Laravel\Socialite\Contracts\Provider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('laravelpassport')->andReturn($provider);
}

test('the first user of a fresh installation is created as an admin', function () {
    fakeOidcUser();

    $response = $this->get(route('login.oidc.callback', ['connection' => 'sso']));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::sole();

    expect($user->email)->toBe('ada@example.com')
        ->and($user->first_name)->toBe('Ada')
        ->and($user->last_name)->toBe('Lovelace')
        ->and($user->role)->toBe(UserRole::ADMIN)
        ->and($user->oidc_provider)->toBe('sso')
        ->and($user->oidc_sub)->toBe('sub-123');
});

test('an existing account is linked to the oidc identity on first sign in', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    fakeOidcUser();

    $this->get(route('login.oidc.callback', ['connection' => 'sso']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    expect($user->fresh()->oidc_sub)->toBe('sub-123');
});

test('a linked account is matched on sub even when the email has changed', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'oidc_provider' => 'sso',
        'oidc_sub' => 'sub-123',
    ]);

    fakeOidcUser(['email' => 'ada.lovelace@example.com']);

    $this->get(route('login.oidc.callback', ['connection' => 'sso']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    // The local address is authoritative; a changed IdP email must not
    // silently rewrite it, nor collide with another account's address.
    expect($user->fresh()->email)->toBe('ada@example.com');
});

test('unknown users are rejected once the installation has users', function () {
    User::factory()->create(['email' => 'grace@example.com']);

    fakeOidcUser();

    $this->get(route('login.oidc.callback', ['connection' => 'sso']))
        ->assertForbidden();

    $this->assertGuest();
    expect(User::count())->toBe(1);
});

test('a provider that returns no email is rejected', function () {
    User::factory()->create();

    fakeOidcUser(['email' => null]);

    $this->get(route('login.oidc.callback', ['connection' => 'sso']))
        ->assertForbidden();

    $this->assertGuest();
});

test('two accounts cannot hold the same oidc identity', function () {
    User::factory()->create(['oidc_provider' => 'sso', 'oidc_sub' => 'sub-123']);

    expect(fn () => User::factory()->create(['oidc_provider' => 'sso', 'oidc_sub' => 'sub-123']))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

test('accounts without an oidc identity do not collide', function () {
    User::factory()->count(3)->create();

    expect(User::whereNull('oidc_sub')->count())->toBe(3);
});

test('oidc drivers cannot be completed through the stateless socialite routes', function () {
    // The provider package mirrors connections into services.oidc_sso, which
    // would otherwise satisfy the catch-all routes' configured-driver check and
    // let the flow run stateless, skipping state, nonce and PKCE validation.
    expect(config('services.oidc_sso.client_id'))->toBe('envault');

    $this->get('/login/oidc_sso')->assertNotFound();
    $this->get('/login/oidc_sso/callback')->assertNotFound();
});

test('unconfigured connections are not routable', function () {
    $this->get(route('login.oidc.start', ['connection' => 'nope']))->assertNotFound();
    $this->get(route('login.oidc.callback', ['connection' => 'nope']))->assertNotFound();
});

test('configured connections are shared with the login screen', function () {
    User::factory()->create();

    $this->get(route('auth.email-code'))
        ->assertInertia(fn ($page) => $page
            ->where('features.oidcSso.0.name', 'sso')
            ->where('features.oidcSso.0.label', 'Sign in with Example')
        );
});

test('passport and oidc are offered side by side when both are configured', function () {
    config([
        'services.laravelpassport.client_id' => 'passport-client',
        'services.laravelpassport.label' => 'Sign in with Passport',
        'services.laravelpassport.logo' => '/images/sso.svg',
    ]);

    User::factory()->create();

    $this->get(route('auth.email-code'))
        ->assertInertia(fn ($page) => $page
            ->where('features.laravelPassportSso.label', 'Sign in with Passport')
            ->where('features.oidcSso.0.label', 'Sign in with Example')
        );
});

test('one account can sign in through either provider', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    fakeOidcUser();
    fakePassportUser();

    $this->get(route('login.oidc.callback', ['connection' => 'sso']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'));
    $this->assertGuest();

    $this->get(route('login.socialite.callback', ['driver' => 'laravelpassport']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    // The Passport flow matches on email and never touches the OIDC columns,
    // so the identity linked by the OIDC flow survives.
    expect($user->fresh()->oidc_sub)->toBe('sub-123');
});
