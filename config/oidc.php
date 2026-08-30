<?php

/*
|--------------------------------------------------------------------------
| OpenID Connect single sign-on
|--------------------------------------------------------------------------
|
| Each connection below is registered by socialiteproviders/openidconnect
| as a Socialite driver named "oidc_{connection}". Endpoints and signing
| keys are discovered from the issuer's /.well-known/openid-configuration,
| so only the client credentials and the issuer location are needed here.
|
| This is additive: the existing "laravelpassport" SSO driver is untouched
| and can run alongside any connection configured here.
|
*/

$connections = [

    'sso' => [
        // generic (default), entra, keycloak, auth0, okta or google.
        'provider' => env('OIDC_PROVIDER'),

        // Generic issuers: the issuer URL, e.g. https://id.example.com.
        'base_url' => env('OIDC_BASE_URL'),

        // Built-in providers derive their base_url from these instead.
        'tenant' => env('OIDC_TENANT'),         // entra
        'server_url' => env('OIDC_SERVER_URL'), // keycloak
        'realm' => env('OIDC_REALM'),           // keycloak
        'domain' => env('OIDC_DOMAIN'),         // auth0 / okta

        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'redirect' => env('OIDC_REDIRECT_URI', '/login/oidc/sso/callback'),

        'scopes' => array_filter(explode(' ', (string) env('OIDC_SCOPES', 'openid email profile'))),

        // envault matches accounts on email, so an email claim is mandatory.
        'require_email' => true,

        // Shown on the login screen.
        'label' => env('OIDC_LABEL', 'Sign in with SSO'),
        'logo' => env('OIDC_LOGO', '/images/sso.svg'),
    ],

];

/*
 * Drop connections that have not been configured, and strip null keys so the
 * built-in providers can apply their own defaults.
 */
$connections = array_map(
    fn (array $connection): array => array_filter($connection, fn ($value): bool => $value !== null && $value !== []),
    $connections,
);

$connections = array_filter(
    $connections,
    fn (array $connection): bool => filled($connection['client_id'] ?? null),
);

return [

    'driver_prefix' => 'oidc_',

    'connections' => $connections,

];
