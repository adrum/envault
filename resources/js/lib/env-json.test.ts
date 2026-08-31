import { describe, expect, it } from "vitest";

import {
  coerceJsonValue,
  envToNestedJson,
  formatEnvValue,
  keysLookNested,
  nestedJsonToEnv,
  parseEnvFile,
  serializeEnvFile,
} from "./env-json";

describe("parseEnvFile", () => {
  it("reads bare values and trims surrounding whitespace", () => {
    expect(parseEnvFile("KEY = value  ")).toEqual([
      { key: "KEY", value: "value", quoted: false },
    ]);
  });

  it("skips comments and blank lines", () => {
    expect(parseEnvFile("# a comment\n\nKEY=value\n")).toEqual([
      { key: "KEY", value: "value", quoted: false },
    ]);
  });

  it("records whether a value was quoted", () => {
    expect(parseEnvFile('KEY="value"')[0].quoted).toBe(true);
    expect(parseEnvFile("KEY='value'")[0].quoted).toBe(true);
    expect(parseEnvFile("KEY=value")[0].quoted).toBe(false);
  });

  it("expands escapes inside double quotes but not single quotes", () => {
    expect(parseEnvFile('KEY="a\\nb\\tc\\\\d\\"e"')[0].value).toBe(
      'a\nb\tc\\d"e',
    );
    expect(parseEnvFile("KEY='a\\nb'")[0].value).toBe("a\\nb");
  });

  it("keeps a quoted value's newlines", () => {
    expect(parseEnvFile('KEY="line one\nline two"')[0].value).toBe(
      "line one\nline two",
    );
  });

  it("ignores lines with no assignment", () => {
    expect(parseEnvFile("NOT_AN_ASSIGNMENT\nKEY=value")).toEqual([
      { key: "KEY", value: "value", quoted: false },
    ]);
  });
});

describe("formatEnvValue", () => {
  it("leaves simple values bare", () => {
    expect(formatEnvValue("value")).toBe("value");
    expect(formatEnvValue("")).toBe("");
  });

  it("quotes and escapes values that need it", () => {
    expect(formatEnvValue("a b")).toBe("a b");
    expect(formatEnvValue(" padded ")).toBe('" padded "');
    expect(formatEnvValue("has#hash")).toBe('"has#hash"');
    expect(formatEnvValue('has"quote')).toBe('"has\\"quote"');
    expect(formatEnvValue("has\\backslash")).toBe('"has\\\\backslash"');
    expect(formatEnvValue("two\nlines")).toBe('"two\nlines"');
  });

  it("round trips through parseEnvFile", () => {
    for (const value of [
      "plain",
      " padded ",
      'with"quote',
      "with\\backslash",
      "two\nlines",
      "with#hash",
    ]) {
      const [entry] = parseEnvFile(`KEY=${formatEnvValue(value)}`);
      expect(entry.value, `round trip of ${JSON.stringify(value)}`).toBe(value);
    }
  });
});

describe("coerceJsonValue", () => {
  it("coerces bare literals to JSON types", () => {
    expect(coerceJsonValue("true")).toBe(true);
    expect(coerceJsonValue("false")).toBe(false);
    expect(coerceJsonValue("null")).toBe(null);
    expect(coerceJsonValue("42")).toBe(42);
    expect(coerceJsonValue("-7")).toBe(-7);
    expect(coerceJsonValue("1.5")).toBe(1.5);
  });

  it("leaves quoted values as strings", () => {
    expect(coerceJsonValue("true", true)).toBe("true");
    expect(coerceJsonValue("null", true)).toBe("null");
    expect(coerceJsonValue("42", true)).toBe("42");
  });

  it("leaves values that are not literals alone", () => {
    expect(coerceJsonValue("")).toBe("");
    expect(coerceJsonValue("hello")).toBe("hello");
    expect(coerceJsonValue("007")).toBe(7);
    expect(coerceJsonValue("1.2.3")).toBe("1.2.3");
  });

  it("does not coerce integers beyond safe precision", () => {
    expect(coerceJsonValue("9007199254740993")).toBe("9007199254740993");
  });
});

describe("envToNestedJson", () => {
  it("nests on double underscores", () => {
    expect(JSON.parse(envToNestedJson("Logging__Level=Debug"))).toEqual({
      Logging: { Level: "Debug" },
    });
  });

  it("builds arrays from numeric segments", () => {
    const json = JSON.parse(
      envToNestedJson("Hosts__0=alpha\nHosts__1=beta"),
    ) as { Hosts: string[] };

    expect(Array.isArray(json.Hosts)).toBe(true);
    expect(json.Hosts).toEqual(["alpha", "beta"]);
  });

  it("applies literal coercion, with quoting as the escape hatch", () => {
    expect(
      JSON.parse(
        envToNestedJson(
          [
            "A__Enabled=true",
            "A__Retries=3",
            "A__Missing=null",
            'A__Literal="null"',
          ].join("\n"),
        ),
      ),
    ).toEqual({
      A: { Enabled: true, Retries: 3, Missing: null, Literal: "null" },
    });
  });
});

describe("nestedJsonToEnv", () => {
  it("flattens nested objects and arrays", () => {
    expect(
      nestedJsonToEnv('{"Logging":{"Level":"Debug"},"Hosts":["a","b"]}'),
    ).toBe("Logging__Level=Debug\nHosts__0=a\nHosts__1=b");
  });

  it("writes scalars bare so they coerce back to the same type", () => {
    expect(nestedJsonToEnv('{"A":true,"B":null,"C":42,"D":1.5}')).toBe(
      "A=true\nB=null\nC=42\nD=1.5",
    );
  });

  it("quotes strings that would otherwise be coerced", () => {
    expect(nestedJsonToEnv('{"A":"null","B":"true","C":"42"}')).toBe(
      'A="null"\nB="true"\nC="42"',
    );
  });
});

describe("round trip", () => {
  const cases: Record<string, unknown> = {
    "objects and arrays": {
      Logging: { Level: "Debug", Nested: { Deep: "value" } },
      Hosts: ["alpha", "beta"],
    },
    "scalar types": { Enabled: true, Disabled: false, Missing: null, N: 42 },
    "strings that look like literals": {
      A: "null",
      B: "true",
      C: "false",
      D: "42",
      E: "1.5",
    },
    "awkward strings": { A: "with space", B: "with#hash", C: "" },
  };

  for (const [name, value] of Object.entries(cases)) {
    it(`preserves ${name} through json -> env -> json`, () => {
      const json = JSON.stringify(value);

      expect(JSON.parse(envToNestedJson(nestedJsonToEnv(json)))).toEqual(value);
    });
  }
});

describe("keysLookNested", () => {
  it("detects double underscores between segments", () => {
    expect(keysLookNested(["Logging__Level"])).toBe(true);
    expect(keysLookNested(["DATABASE_URL", "APP_KEY"])).toBe(false);
    expect(keysLookNested(["__LEADING"])).toBe(false);
  });
});

describe("serializeEnvFile", () => {
  it("writes entries back out one per line", () => {
    expect(
      serializeEnvFile([
        { key: "A", value: "one" },
        { key: "B", value: "two words" },
      ]),
    ).toBe("A=one\nB=two words");
  });
});
