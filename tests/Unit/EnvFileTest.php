<?php

use App\Support\EnvFile;

test('parses bare key/value pairs', function () {
    $entries = EnvFile::parse("FOO=bar\nBAZ=qux");

    expect($entries)->toEqual([
        ['key' => 'FOO', 'value' => 'bar'],
        ['key' => 'BAZ', 'value' => 'qux'],
    ]);
});

test('strips surrounding double and single quotes', function () {
    $entries = EnvFile::parse("FOO=\"bar\"\nBAZ='qux'");

    expect($entries)->toEqual([
        ['key' => 'FOO', 'value' => 'bar'],
        ['key' => 'BAZ', 'value' => 'qux'],
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
        ],
        ['key' => 'NEXT', 'value' => 'after'],
    ]);
});

test('parses single-quoted multiline value', function () {
    $env = "PUB='-----BEGIN PUBLIC KEY-----\nABC\n-----END PUBLIC KEY-----'";

    $entries = EnvFile::parse($env);

    expect($entries[0]['value'])->toEqual("-----BEGIN PUBLIC KEY-----\nABC\n-----END PUBLIC KEY-----");
});

test('skips comments and blank lines', function () {
    $env = "# comment\n\nFOO=bar\n# trailing";

    expect(EnvFile::parse($env))->toEqual([
        ['key' => 'FOO', 'value' => 'bar'],
    ]);
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
    $serialized = EnvFile::serialize([['key' => 'KEY', 'value' => $value]]);
    $parsed = EnvFile::parse($serialized);

    expect($parsed)->toEqual([['key' => 'KEY', 'value' => $value]]);
});
