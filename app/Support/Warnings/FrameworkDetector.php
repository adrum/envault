<?php

namespace App\Support\Warnings;

class FrameworkDetector
{
    /**
     * Heuristically detect which application frameworks an environment's
     * key/value map likely belongs to. Multiple frameworks can be returned
     * when an environment mixes signals (e.g. a polyglot deployment).
     *
     * @param  array<string, string>  $values
     * @return list<string>
     */
    public function detect(array $values): array
    {
        $keys = array_keys($values);
        $detected = [];

        if ($this->isLaravel($keys)) {
            $detected[] = 'laravel';
        }

        if ($this->isDotNet($keys)) {
            $detected[] = 'dotnet';
        }

        return $detected;
    }

    /**
     * @param  list<string>  $keys
     */
    private function isLaravel(array $keys): bool
    {
        $signals = ['APP_KEY', 'APP_ENV', 'APP_DEBUG', 'APP_URL'];

        foreach ($signals as $signal) {
            if (in_array($signal, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keys
     */
    private function isDotNet(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($key === 'ASPNETCORE_ENVIRONMENT' || $key === 'DOTNET_ENVIRONMENT') {
                return true;
            }

            if (str_contains($key, '__')) {
                return true;
            }
        }

        return false;
    }
}
