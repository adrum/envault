// Conversion between .env content and .NET-style nested JSON (appsettings.json).
//
// Extracted from the app editor so the pure string-in/string-out logic can be
// unit tested; the editor imports these rather than defining them inline.

// Parse a .env-formatted string. Mirrors App\Support\EnvFile::parse on the
// backend so the frontend bulk editor and the import endpoint behave identically.
export type EnvEntry = { key: string; value: string; quoted?: boolean };

export const parseEnvFile = (content: string): EnvEntry[] => {
  const out: EnvEntry[] = [];
  const len = content.length;
  let i = 0;
  const advancePastNewline = (idx: number): number => {
    if (idx < len && content[idx] === "\r") idx++;
    if (idx < len && content[idx] === "\n") idx++;
    return idx;
  };
  while (i < len) {
    while (i < len && (content[i] === " " || content[i] === "\t")) i++;
    if (i >= len || content[i] === "\n" || content[i] === "\r") {
      i = advancePastNewline(i);
      continue;
    }
    if (content[i] === "#") {
      while (i < len && content[i] !== "\n") i++;
      i = advancePastNewline(i);
      continue;
    }
    const keyStart = i;
    while (i < len && content[i] !== "=" && content[i] !== "\n") i++;
    if (i >= len || content[i] !== "=") {
      i = advancePastNewline(i);
      continue;
    }
    const key = content.substring(keyStart, i).replace(/[ \t]+$/, "");
    i++; // skip '='
    while (i < len && (content[i] === " " || content[i] === "\t")) i++;
    let value = "";
    let quoted = false;
    if (i < len && (content[i] === '"' || content[i] === "'")) {
      quoted = true;
      const quote = content[i];
      i++;
      let buf = "";
      while (i < len) {
        const ch = content[i];
        if (quote === '"' && ch === "\\" && i + 1 < len) {
          const next = content[i + 1];
          buf +=
            next === "n"
              ? "\n"
              : next === "r"
                ? "\r"
                : next === "t"
                  ? "\t"
                  : next === "\\"
                    ? "\\"
                    : next === '"'
                      ? '"'
                      : "\\" + next;
          i += 2;
          continue;
        }
        if (ch === quote) {
          i++;
          break;
        }
        buf += ch;
        i++;
      }
      value = buf;
      while (i < len && content[i] !== "\n") i++;
    } else {
      const valueStart = i;
      while (i < len && content[i] !== "\n") i++;
      value = content.substring(valueStart, i).trim();
    }
    i = advancePastNewline(i);
    if (key === "") continue;
    out.push({ key, value, quoted });
  }
  return out;
};

// Render a value back to its .env representation. Wraps any value containing
// a newline, quote, comment marker, leading/trailing whitespace, or backslash
// in double quotes (with `\\` and `\"` escaped).
export const formatEnvValue = (value: string): string => {
  if (value === "") return "";
  const needsQuotes = /[\n"'#=\\]/.test(value) || value !== value.trim();
  if (!needsQuotes) return value;
  const escaped = value
    .replace(/\\/g, "\\\\")
    .replace(/"/g, '\\"')
    .replace(/\r/g, "\\r");
  return `"${escaped}"`;
};

export const serializeEnvFile = (entries: EnvEntry[]): string =>
  entries.map((e) => `${e.key}=${formatEnvValue(e.value)}`).join("\n");

// Coerce a string env value to a JSON-friendly type for nicer appsettings.json
// output. Quoting is the escape hatch: `KEY=null` becomes JSON null while
// `KEY="null"` stays the string "null" (same for booleans and numbers).
export const coerceJsonValue = (
  raw: string,
  quoted = false,
): string | number | boolean | null => {
  if (raw === "" || quoted) return raw;
  if (raw === "true") return true;
  if (raw === "false") return false;
  if (raw === "null") return null;
  if (/^-?\d+$/.test(raw)) {
    const n = Number(raw);
    if (Number.isSafeInteger(n)) return n;
  }
  if (/^-?\d+\.\d+$/.test(raw)) return Number(raw);
  return raw;
};

// Would this string be coerced to a non-string JSON type if written unquoted?
export const looksLikeJsonLiteral = (value: string): boolean =>
  typeof coerceJsonValue(value) !== "string";

// Convert .NET-style flat env content (Section__Key=value, Section__0__Key=value)
// into a nested JSON string. Numeric path segments produce arrays.
export const envToNestedJson = (env: string): string => {
  const root: Record<string, unknown> = {};
  for (const { key, value, quoted } of parseEnvFile(env)) {
    const parts = key.split("__");
    let cursor: Record<string | number, unknown> = root as Record<
      string | number,
      unknown
    >;
    for (let i = 0; i < parts.length; i++) {
      const part = parts[i];
      const isIndex = /^\d+$/.test(part);
      const segment: string | number = isIndex ? Number(part) : part;
      if (i === parts.length - 1) {
        cursor[segment] = coerceJsonValue(value, quoted);
      } else {
        const nextIsIndex = /^\d+$/.test(parts[i + 1]);
        if (
          cursor[segment] === undefined ||
          typeof cursor[segment] !== "object" ||
          cursor[segment] === null
        ) {
          cursor[segment] = nextIsIndex ? [] : {};
        }
        cursor = cursor[segment] as Record<string | number, unknown>;
      }
    }
  }
  return JSON.stringify(root, null, 2);
};

// Convert a nested JSON string back to the flat .NET-style env representation.
export const nestedJsonToEnv = (json: string): string => {
  const parsed: unknown = JSON.parse(json);
  const lines: string[] = [];
  // Scalars are written bare so they coerce back to the same JSON type; strings
  // that would otherwise be coerced (e.g. "null", "true", "42") are quoted.
  const formatScalar = (v: unknown): string => {
    if (v === undefined) return "";
    if (v === null) return "null";
    if (typeof v === "boolean") return v ? "true" : "false";
    if (typeof v === "string") {
      return looksLikeJsonLiteral(v) ? `"${v}"` : formatEnvValue(v);
    }
    return String(v);
  };
  const walk = (node: unknown, path: string[]): void => {
    if (node === null || typeof node !== "object") {
      lines.push(`${path.join("__")}=${formatScalar(node)}`);
      return;
    }
    if (Array.isArray(node)) {
      node.forEach((item, i) => walk(item, [...path, String(i)]));
      return;
    }
    for (const k of Object.keys(node as Record<string, unknown>)) {
      walk((node as Record<string, unknown>)[k], [...path, k]);
    }
  };
  walk(parsed, []);
  return lines.join("\n");
};

// Heuristic: does this set of keys look like an .NET-style nested config
// (i.e. uses `__` between segments to express hierarchy)?
export const keysLookNested = (keys: string[]): boolean =>
  keys.some((k) => /[A-Za-z0-9]__[A-Za-z0-9]/.test(k));
