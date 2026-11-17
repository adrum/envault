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
     *  - Other comments: # text (kept as a commented entry with an empty key
     *    and the text as its value)
     *  - Blank lines (skipped)
     *
     * Commented entries also carry `prefix`: the comment marker exactly as
     * written, including any whitespace after the `#` (e.g. `#`, `# `, `##  `).
     *
     * @return list<array{key: string, value: string, commented: bool, prefix?: string}>
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
                $comment = rtrim(substr($content, $commentStart, $i - $commentStart));
                preg_match('/^(#+[ \t]*)(.*)$/s', $comment, $parts);
                $entries[] = self::parseCommentedAssignment($parts[2], $parts[1]) ?? [
                    'key' => '',
                    'value' => $parts[2],
                    'commented' => true,
                    'prefix' => $parts[1],
                ];
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
     * the comment. Entries with an empty key are plain comments: `# text`.
     * A commented entry's `prefix` is written as-is; without one, `# ` is used.
     *
     * @param  iterable<array{key: string, value: string, commented?: bool, prefix?: string|null}>  $entries
     */
    public static function serialize(iterable $entries): string
    {
        $lines = [];
        foreach ($entries as $entry) {
            $prefix = $entry['prefix'] ?? '# ';
            if ($entry['key'] === '') {
                foreach (explode("\n", $entry['value']) as $text) {
                    $lines[] = rtrim($prefix . $text);
                }

                continue;
            }
            $value = self::formatValue($entry['value']);
            if ($entry['commented'] ?? false) {
                $lines[] = $prefix . $entry['key'] . '=' . str_replace("\n", '\\n', $value);

                continue;
            }
            $lines[] = $entry['key'] . '=' . $value;
        }

        return implode("\n", $lines);
    }

    /**
     * Drop every uncommented entry whose key is assigned again by a later
     * uncommented entry, so the last assignment wins. Commented entries are
     * left alone.
     *
     * @param  list<array{key: string, value: string, commented: bool, prefix?: string}>  $entries
     * @return list<array{key: string, value: string, commented: bool, prefix?: string}>
     */
    public static function lastActiveWins(array $entries): array
    {
        $seen = [];
        $kept = [];
        foreach (array_reverse($entries) as $entry) {
            if (!$entry['commented']) {
                if (isset($seen[$entry['key']])) {
                    continue;
                }
                $seen[$entry['key']] = true;
            }
            $kept[] = $entry;
        }

        return array_reverse($kept);
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
     * Read the text of a comment as a commented-out assignment, or return
     * null when it is a plain comment.
     *
     * @return array{key: string, value: string, commented: bool, prefix: string}|null
     */
    private static function parseCommentedAssignment(string $assignment, string $prefix): ?array
    {
        if (preg_match('/^[A-Za-z0-9_.\-]+[ \t]*=/', $assignment) !== 1) {
            return null;
        }

        $entry = self::parse($assignment)[0] ?? null;

        return $entry === null ? null : [...$entry, 'commented' => true, 'prefix' => $prefix];
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
