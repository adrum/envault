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
     *  - Commented-out assignments: # KEY=value (kept, flagged as commented;
     *    the value never continues past the end of the line)
     *  - Other comments and blank lines (skipped)
     *
     * @return list<array{key: string, value: string, commented: bool}>
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
                $commentStart = $i;
                while ($i < $length && $content[$i] !== "\n") {
                    $i++;
                }
                $entry = self::parseCommentedAssignment(substr($content, $commentStart, $i - $commentStart));
                if ($entry !== null) {
                    $entries[] = $entry;
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
            $entries[] = ['key' => $key, 'value' => $value, 'commented' => false];
        }

        return $entries;
    }

    /**
     * Serialize key/value pairs into .env format. Values containing newlines,
     * leading/trailing whitespace, or quote characters are wrapped in double
     * quotes with `\`, `"`, `\r` escaped (newlines are preserved literally so
     * the output stays human-readable). Commented entries are written as
     * `# KEY=value` with newlines escaped, so the whole value stays inside
     * the comment.
     *
     * @param  iterable<array{key: string, value: string, commented?: bool}>  $entries
     */
    public static function serialize(iterable $entries): string
    {
        $lines = [];
        foreach ($entries as $entry) {
            $value = self::formatValue($entry['value']);
            if ($entry['commented'] ?? false) {
                $lines[] = '# ' . $entry['key'] . '=' . str_replace("\n", '\\n', $value);

                continue;
            }
            $lines[] = $entry['key'] . '=' . $value;
        }

        return implode("\n", $lines);
    }

    /**
     * List the keys that are assigned more than once by uncommented lines.
     *
     * @param  iterable<array{key: string, value: string, commented: bool}>  $entries
     * @return list<string>
     */
    public static function duplicateActiveKeys(iterable $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            if (!$entry['commented']) {
                $counts[$entry['key']] = ($counts[$entry['key']] ?? 0) + 1;
            }
        }

        return array_map(strval(...), array_keys(array_filter($counts, fn (int $count): bool => $count > 1)));
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

    /**
     * Read a comment line as a commented-out assignment, or return null when
     * it is an ordinary comment.
     *
     * @return array{key: string, value: string, commented: bool}|null
     */
    private static function parseCommentedAssignment(string $line): ?array
    {
        $assignment = ltrim($line, "# \t");
        if (preg_match('/^[A-Za-z0-9_.\-]+[ \t]*=/', $assignment) !== 1) {
            return null;
        }

        $entry = self::parse($assignment)[0] ?? null;

        return $entry === null ? null : [...$entry, 'commented' => true];
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
