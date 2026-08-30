<?php

namespace App\Support;

/**
 * Thin reader over config/oidc.php.
 *
 * Every configured connection is registered by socialiteproviders/openidconnect
 * as the Socialite driver "{driver_prefix}{connection}"; this class is the one
 * place that knows how to go from a connection name to that driver, and what to
 * show on the login screen for it.
 */
class OidcConnections
{
    /**
     * The configured connection names, e.g. ['sso'].
     *
     * @return list<string>
     */
    public static function names(): array
    {
        /** @var array<string, array<string, mixed>> $connections */
        $connections = config('oidc.connections', []);

        return array_keys($connections);
    }

    public static function has(string $connection): bool
    {
        return in_array($connection, self::names(), true);
    }

    /**
     * The Socialite driver backing a connection.
     */
    public static function driver(string $connection): string
    {
        return config('oidc.driver_prefix', 'oidc_') . $connection;
    }

    /**
     * Whether a Socialite driver name belongs to an OIDC connection.
     *
     * The package mirrors every connection into services.{driver}, which would
     * otherwise make OIDC drivers reachable through the catch-all Socialite
     * routes. Those run stateless, which skips the state, nonce and PKCE checks
     * the OIDC flow depends on, so they must be refused there.
     */
    public static function isOidcDriver(string $driver): bool
    {
        foreach (self::names() as $connection) {
            if ($driver === self::driver($connection)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The connections as the login screen needs them.
     *
     * @return list<array{name: string, label: string, logo: string|null}>
     */
    public static function forDisplay(): array
    {
        /** @var array<string, array<string, mixed>> $connections */
        $connections = config('oidc.connections', []);

        $display = [];

        foreach ($connections as $name => $connection) {
            $display[] = [
                'name' => $name,
                'label' => (string) ($connection['label'] ?? 'Sign in with SSO'),
                'logo' => isset($connection['logo']) ? (string) $connection['logo'] : null,
            ];
        }

        return $display;
    }
}
