<?php

namespace App\Support\Warnings;

use App\Models\Environment;

class WarningEvaluator
{
    /**
     * Evaluate the configured warning rules against the proposed key/value
     * map for the given environment.
     *
     * @param  array<string, string>  $values
     * @return list<Warning>
     */
    public function evaluate(Environment $environment, array $values): array
    {
        $rules = config('envault.warnings', []);
        $envIdentifiers = $this->environmentIdentifiers($environment);

        $warnings = [];

        foreach ($rules as $rule) {
            $warning = match ($rule['type'] ?? null) {
                'value_warning' => $this->evaluateValueWarning($rule, $values, $envIdentifiers),
                'unknown_value' => $this->evaluateUnknownValue($rule, $values),
                'requires_companions' => $this->evaluateRequiresCompanions($rule, $values),
                default => null,
            };

            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, string>  $values
     * @param  list<string>  $envIdentifiers
     */
    private function evaluateValueWarning(array $rule, array $values, array $envIdentifiers): ?Warning
    {
        $key = $rule['key'];

        if (!array_key_exists($key, $values)) {
            return null;
        }

        $value = strtolower(trim($values[$key]));
        $candidates = array_map('strtolower', $rule['values'] ?? []);

        if (!in_array($value, $candidates, true)) {
            return null;
        }

        if (!empty($rule['only_in']) && !$this->envMatches($envIdentifiers, $rule['only_in'])) {
            return null;
        }

        if (!empty($rule['except_in']) && $this->envMatches($envIdentifiers, $rule['except_in'])) {
            return null;
        }

        return new Warning($rule['message'], [$key]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function evaluateUnknownValue(array $rule, array $values): ?Warning
    {
        $key = $rule['key'];

        if (!array_key_exists($key, $values)) {
            return null;
        }

        $raw = trim($values[$key]);

        if ($raw === '') {
            return null;
        }

        $allowed = array_map('strtolower', $rule['allowed'] ?? []);

        if (in_array(strtolower($raw), $allowed, true)) {
            return null;
        }

        return new Warning($rule['message'], [$key]);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function evaluateRequiresCompanions(array $rule, array $values): ?Warning
    {
        $key = $rule['key'];

        if (!array_key_exists($key, $values)) {
            return null;
        }

        if (strtolower(trim($values[$key])) !== strtolower($rule['value'])) {
            return null;
        }

        $missing = [];
        foreach ($rule['requires'] ?? [] as $companion) {
            if (!array_key_exists($companion, $values) || trim($values[$companion]) === '') {
                $missing[] = $companion;
            }
        }

        if (empty($missing)) {
            return null;
        }

        $message = $rule['message'] . ' Missing: ' . implode(', ', $missing) . '.';

        return new Warning($message, [$key, ...$missing]);
    }

    /**
     * @return list<string>
     */
    private function environmentIdentifiers(Environment $environment): array
    {
        $identifiers = [
            $environment->slug,
            $environment->environmentType->name ?? null,
            $environment->custom_label,
        ];

        return array_values(array_unique(array_map(
            fn ($v) => strtolower((string) $v),
            array_filter($identifiers, fn ($v) => !empty($v)),
        )));
    }

    /**
     * @param  list<string>  $envIdentifiers
     * @param  list<string>  $needles
     */
    private function envMatches(array $envIdentifiers, array $needles): bool
    {
        $needles = array_map('strtolower', $needles);

        foreach ($envIdentifiers as $identifier) {
            if (in_array($identifier, $needles, true)) {
                return true;
            }
        }

        return false;
    }
}
