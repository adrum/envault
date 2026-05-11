<?php

namespace App\Support;

class EnvFile
{
    /**
     * Parse a .env-formatted string into an ordered list of key/value pairs.
     *
     * Supports:
     *  - Bare values: KEY=value
     *  - Single-quoted values: KEY='value' (literal contents, may span lines)
     *  - Double-quoted values: KEY="value" (may span lines; \n \r \t \\ \" expanded)
     *  - Comments (# at start of line) and blank lines (skipped)
     *
     * @return list<array{key: string, value: string}>
     */
    public static function parse(string $content): array
    {
        $entries = [];
        $length = strlen($content);
        $i = 0;

        while ($i < $length) {
            // Skip leading whitespace on the line (but not newlines yet).
            while ($i < $length && ($content[$i] === ' ' || $content[$i] === "\t")) {
                $i++;
            }

            // Blank line.
            if ($i >= $length || $content[$i] === "\n" || $content[$i] === "\r") {
                $i = self::advancePastNewline($content, $i);

                continue;
            }

            // Comment.
            if ($content[$i] === '#') {
                while ($i < $length && $content[$i] !== "\n") {
                    $i++;
                }
                $i = self::advancePastNewline($content, $i);

                continue;
            }

            // Read key up to '='.
            $keyStart = $i;
            while ($i < $length && $content[$i] !== '=' && $content[$i] !== "\n") {
                $i++;
            }
            if ($i >= $length || $content[$i] !== '=') {
                $i = self::advancePastNewline($content, $i);

                continue;
            }
            $key = rtrim(substr($content, $keyStart, $i - $keyStart));
            $i++; // skip '='

            // Skip spaces/tabs after '='.
            while ($i < $length && ($content[$i] === ' ' || $content[$i] === "\t")) {
                $i++;
            }

            $value = '';
            if ($i < $length && ($content[$i] === '"' || $content[$i] === "'")) {
                $quote = $content[$i];
                $i++;
                $valueStart = $i;
                $buffer = '';
                while ($i < $length) {
                    $ch = $content[$i];
                    if ($quote === '"' && $ch === '\\' && $i + 1 < $length) {
                        $next = $content[$i + 1];
                        $buffer .= match ($next) {
                            'n' => "\n",
                            'r' => "\r",
                            't' => "\t",
                            '\\' => '\\',
                            '"' => '"',
                            default => '\\' . $next,
                        };
                        $i += 2;

                        continue;
                    }
                    if ($ch === $quote) {
                        $i++;
                        break;
                    }
                    $buffer .= $ch;
                    $i++;
                }
                $value = $buffer;
                // Skip rest of the line.
                while ($i < $length && $content[$i] !== "\n") {
                    $i++;
                }
            } else {
                $valueStart = $i;
                while ($i < $length && $content[$i] !== "\n") {
                    $i++;
                }
                $value = trim(substr($content, $valueStart, $i - $valueStart));
            }

            $i = self::advancePastNewline($content, $i);
            if ($key === '') {
                continue;
            }
            $entries[] = ['key' => $key, 'value' => $value];
        }

        return $entries;
    }

    /**
     * Serialize key/value pairs into .env format. Values containing newlines,
     * leading/trailing whitespace, or quote characters are wrapped in double
     * quotes with `\`, `"`, `\r` escaped (newlines are preserved literally so
     * the output stays human-readable).
     *
     * @param  iterable<array{key: string, value: string}>  $entries
     */
    public static function serialize(iterable $entries): string
    {
        $lines = [];
        foreach ($entries as $entry) {
            $lines[] = $entry['key'] . '=' . self::formatValue($entry['value']);
        }

        return implode("\n", $lines);
    }

    public static function formatValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $needsQuotes = preg_match('/[\n"\'#=\\\\]/', $value) === 1
            || $value !== trim($value);
        if (!$needsQuotes) {
            return $value;
        }
        $escaped = str_replace(['\\', '"', "\r"], ['\\\\', '\\"', '\\r'], $value);

        return '"' . $escaped . '"';
    }

    private static function advancePastNewline(string $content, int $i): int
    {
        $length = strlen($content);
        if ($i < $length && $content[$i] === "\r") {
            $i++;
        }
        if ($i < $length && $content[$i] === "\n") {
            $i++;
        }

        return $i;
    }
}
