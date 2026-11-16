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

test('skips prose comments and blank lines', function () {
    $env = "# comment\n\nFOO=bar\n# see https://example.com/?a=b\n# trailing";

    expect(EnvFile::parse($env))->toEqual([
        ['key' => 'FOO', 'value' => 'bar', 'commented' => false],
    ]);
});

test('keeps commented-out assignments and flags them', function () {
    $env = "FOO=bar\n# FOO=old\n#BAZ=\"quoted # value\"\n  ## QUX = spaced";

    expect(EnvFile::parse($env))->toEqual([
        ['key' => 'FOO', 'value' => 'bar', 'commented' => false],
        ['key' => 'FOO', 'value' => 'old', 'commented' => true],
        ['key' => 'BAZ', 'value' => 'quoted # value', 'commented' => true],
        ['key' => 'QUX', 'value' => 'spaced', 'commented' => true],
    ]);
});

test('a commented-out value never continues onto the next line', function () {
    $env = "# FOO=\"unterminated\nBAR=next";

    expect(EnvFile::parse($env))->toEqual([
        ['key' => 'FOO', 'value' => 'unterminated', 'commented' => true],
        ['key' => 'BAR', 'value' => 'next', 'commented' => false],
    ]);
});

test('serialize writes commented entries on a single comment line', function () {
    $entries = [
        ['key' => 'A', 'value' => 'simple', 'commented' => true],
        ['key' => 'B', 'value' => "line1\nline2", 'commented' => true],
    ];

    $out = EnvFile::serialize($entries);

    expect($out)->toEqual("# A=simple\n# B=\"line1\\nline2\"");
    expect(EnvFile::parse($out))->toEqual($entries);
});

test('reports keys assigned by more than one uncommented line', function () {
    $entries = EnvFile::parse("FOO=1\nBAR=1\nFOO=2\n# BAR=2\n# BAZ=1\n# BAZ=2");

    expect(EnvFile::duplicateActiveKeys($entries))->toEqual(['FOO']);
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
