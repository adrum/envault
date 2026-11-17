<?php

use App\Support\EnvFile;

test('parses bare key/value pairs', function () {
    $entries = EnvFile::parse("FOO=bar\nBAZ=qux");

    expect($entries)->toEqual([
        ['key' => 'FOO', 'value' => 'bar', 'commented' => false],
        ['key' => 'BAZ', 'value' => 'qux', 'commented' => false],
    ]);
});

test('strips surrounding double and single quotes', function () {
    $entries = EnvFile::parse("FOO=\"bar\"\nBAZ='qux'");

    expect($entries)->toEqual([
        ['key' => 'FOO', 'value' => 'bar', 'commented' => false],
        ['key' => 'BAZ', 'value' => 'qux', 'commented' => false],
    ]);
});

test('expands escape sequences inside double-quoted values', function () {
    $entries = EnvFile::parse('FOO="line1\nline2\t!"');

    expect($entries[0]['value'])->toEqual("line1\nline2\t!");
});

test('preserves literal contents in single-quoted values', function () {
    $entries = EnvFile::parse("FOO='line1\\nline2'");

    expect($entries[0]['value'])->toEqual('line1\\nline2');
});

test('parses double-quoted multiline value (PEM key)', function () {
    $env = "PASSPORT_PRIVATE_KEY=\"-----BEGIN RSA PRIVATE KEY-----\nMIIBOgIBAAJBAKj==\n-----END RSA PRIVATE KEY-----\"\n\nNEXT=after";

    $entries = EnvFile::parse($env);

    expect($entries)->toEqual([
        [
            'key' => 'PASSPORT_PRIVATE_KEY',
            'value' => "-----BEGIN RSA PRIVATE KEY-----\nMIIBOgIBAAJBAKj==\n-----END RSA PRIVATE KEY-----",
            'commented' => false,
        ],
        ['key' => 'NEXT', 'value' => 'after', 'commented' => false],
    ]);
});

test('parses single-quoted multiline value', function () {
    $env = "PUB='-----BEGIN PUBLIC KEY-----\nABC\n-----END PUBLIC KEY-----'";

    $entries = EnvFile::parse($env);

    expect($entries[0]['value'])->toEqual("-----BEGIN PUBLIC KEY-----\nABC\n-----END PUBLIC KEY-----");
});

test('keeps plain comments under an empty key and skips blank lines', function () {
    $env = "# comment\n\nFOO=bar\n#see https://example.com/?a=b\n#";

    expect(EnvFile::parse($env))->toEqual([
        ['key' => '', 'value' => 'comment', 'commented' => true, 'prefix' => '# '],
        ['key' => 'FOO', 'value' => 'bar', 'commented' => false],
        ['key' => '', 'value' => 'see https://example.com/?a=b', 'commented' => true, 'prefix' => '#'],
        ['key' => '', 'value' => '', 'commented' => true, 'prefix' => '#'],
    ]);
});

test('serialize writes plain comments back as comment lines', function () {
    $entries = [
        ['key' => '', 'value' => 'Database', 'commented' => true, 'prefix' => '# '],
        ['key' => '', 'value' => '', 'commented' => true, 'prefix' => '#'],
        ['key' => 'FOO', 'value' => 'bar', 'commented' => false],
    ];

    $out = EnvFile::serialize($entries);

    expect($out)->toEqual("# Database\n#\nFOO=bar");
    expect(EnvFile::parse($out))->toEqual($entries);
});

test('keeps commented-out assignments and flags them', function () {
    $env = "FOO=bar\n# FOO=old\n#BAZ=\"quoted # value\"\n  ## QUX = spaced";

    expect(EnvFile::parse($env))->toEqual([
        ['key' => 'FOO', 'value' => 'bar', 'commented' => false],
        ['key' => 'FOO', 'value' => 'old', 'commented' => true, 'prefix' => '# '],
        ['key' => 'BAZ', 'value' => 'quoted # value', 'commented' => true, 'prefix' => '#'],
        ['key' => 'QUX', 'value' => 'spaced', 'commented' => true, 'prefix' => '## '],
    ]);
});

test('a commented-out value never continues onto the next line', function () {
    $env = "# FOO=\"unterminated\nBAR=next";

    expect(EnvFile::parse($env))->toEqual([
        ['key' => 'FOO', 'value' => 'unterminated', 'commented' => true, 'prefix' => '# '],
        ['key' => 'BAR', 'value' => 'next', 'commented' => false],
    ]);
});

test('serialize writes commented entries on a single comment line', function () {
    $entries = [
        ['key' => 'A', 'value' => 'simple', 'commented' => true, 'prefix' => '# '],
        ['key' => 'B', 'value' => "line1\nline2", 'commented' => true, 'prefix' => '# '],
    ];

    $out = EnvFile::serialize($entries);

    expect($out)->toEqual("# A=simple\n# B=\"line1\\nline2\"");
    expect(EnvFile::parse($out))->toEqual($entries);
});

test('keeps the whitespace between the comment marker and its contents', function () {
    $env = "#A=1\n# B=2\n#   C=3\n#\tD=4\n#tight note\n#    indented note\n## heading";

    expect(EnvFile::serialize(EnvFile::parse($env)))->toEqual($env);
});

test('a commented entry without a recorded prefix is written with one space', function () {
    $out = EnvFile::serialize([
        ['key' => 'A', 'value' => '1', 'commented' => true],
        ['key' => '', 'value' => 'note', 'commented' => true, 'prefix' => null],
    ]);

    expect($out)->toEqual("# A=1\n# note");
});

test('the last uncommented assignment of a key wins', function () {
    $entries = EnvFile::parse("FOO=1\nBAR=1\nFOO=2\n# BAR=2\n# BAZ=1\n# BAZ=2");

    expect(EnvFile::serialize(EnvFile::lastActiveWins($entries)))
        ->toEqual("BAR=1\nFOO=2\n# BAR=2\n# BAZ=1\n# BAZ=2");
});

test('serialize quotes multiline values and preserves newlines', function () {
    $out = EnvFile::serialize([
        ['key' => 'A', 'value' => 'simple'],
        ['key' => 'B', 'value' => "line1\nline2"],
    ]);

    expect($out)->toEqual("A=simple\nB=\"line1\nline2\"");
});

test('serialize escapes backslashes and quotes', function () {
    $out = EnvFile::formatValue('say "hi" \\ go');

    expect($out)->toEqual('"say \\"hi\\" \\\\ go"');
});

test('parse and serialize round-trip a PEM key', function () {
    $value = "-----BEGIN RSA PRIVATE KEY-----\nMIIB\n-----END RSA PRIVATE KEY-----";
    $serialized = EnvFile::serialize([['key' => 'KEY', 'value' => $value, 'commented' => false]]);
    $parsed = EnvFile::parse($serialized);

    expect($parsed)->toEqual([['key' => 'KEY', 'value' => $value, 'commented' => false]]);
});
