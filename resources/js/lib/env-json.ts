// Conversion between .env content and .NET-style nested JSON (appsettings.json).
//
// Extracted from the app editor so the pure string-in/string-out logic can be
// unit tested; the editor imports these rather than defining them inline.

// Parse a .env-formatted string. Mirrors App\Support\EnvFile::parse on the
// backend so the frontend bulk editor and the import endpoint behave identically.
// Commented-out assignments (`# KEY=value`) are kept and flagged `commented`;
// any other comment is kept as a commented entry with an empty key and the
// comment text as its value. Commented entries carry `prefix`: the comment
// marker exactly as written, including any whitespace after the `#`.
export type EnvEntry = {
  key: string;
  value: string;
  quoted?: boolean;
  commented?: boolean;
  prefix?: string | null;
};

const DEFAULT_COMMENT_PREFIX = "# ";

const COMMENTED_ASSIGNMENT = /^[A-Za-z0-9_.-]+[ \t]*=/;

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
      const commentStart = i;
      while (i < len && content[i] !== "\n") i++;
      // A commented-out value never continues past the end of its line.
      const [, prefix, text] = /^(#+[ \t]*)([^]*)$/.exec(
        content.substring(commentStart, i).trimEnd(),
      )!;
      const [entry] = COMMENTED_ASSIGNMENT.test(text) ? parseEnvFile(text) : [];
      out.push(
        entry
          ? { ...entry, commented: true, prefix }
          : { key: "", value: text, commented: true, prefix },
      );
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

// Commented entries are written as `# KEY=value` with newlines escaped so the
// whole value stays inside the comment. Entries with an empty key are plain
// comments: `# text`. A commented entry's prefix is written as-is; without
// one, `# ` is used.
export const serializeEnvFile = (entries: EnvEntry[]): string =>
  entries
    .map((e) => {
      const prefix = e.prefix ?? DEFAULT_COMMENT_PREFIX;
      if (e.key === "") {
        return e.value
          .split("\n")
          .map((text) => `${prefix}${text}`.trimEnd())
          .join("\n");
      }
      return e.commented
        ? `${prefix}${e.key}=${formatEnvValue(e.value).replace(/\n/g, "\\n")}`
        : `${e.key}=${formatEnvValue(e.value)}`;
    })
    .join("\n");

// Keys assigned by more than one uncommented line. Commented-out lines may
// repeat a key freely.
export const duplicateActiveKeys = (entries: EnvEntry[]): string[] => {
  const counts = new Map<string, number>();
  for (const { key, commented } of entries) {
    if (!commented) counts.set(key, (counts.get(key) ?? 0) + 1);
  }
  return [...counts].filter(([, count]) => count > 1).map(([key]) => key);
};

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

type JsonScalar = string | number | boolean | null;
type JsonContainer = Record<string | number, unknown>;
// A comment inside a container, shown once `after` of its members are printed.
type JsonNote = { after: number; text: string };

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

const isContainer = (v: unknown): v is JsonContainer =>
  typeof v === "object" && v !== null;

const memberCount = (node: JsonContainer): number =>
  Array.isArray(node) ? node.length : Object.keys(node).length;

// Pretty-print like JSON.stringify(value, null, 2), with any recorded
// comments placed between the members of their container.
const printJson = (
  node: unknown,
  notes: Map<JsonContainer, JsonNote[]>,
  indent = "",
): string => {
  if (!isContainer(node)) return JSON.stringify(node ?? null);
  const isArray = Array.isArray(node);
  const keys: (string | number)[] = isArray
    ? [...node.keys()]
    : Object.keys(node);
  const own = notes.get(node) ?? [];
  if (keys.length === 0 && own.length === 0) return isArray ? "[]" : "{}";
  const pad = `${indent}  `;
  const lines: string[] = [];
  keys.forEach((key, index) => {
    for (const note of own) {
      if (note.after === index) lines.push(pad + note.text);
    }
    const label = isArray ? "" : `${JSON.stringify(key)}: `;
    const comma = index < keys.length - 1 ? "," : "";
    lines.push(pad + label + printJson(node[key], notes, pad) + comma);
  });
  for (const note of own) {
    if (note.after >= keys.length) lines.push(pad + note.text);
  }
  return `${isArray ? "[" : "{"}\n${lines.join("\n")}\n${indent}${isArray ? "]" : "}"}`;
};

// Convert .NET-style flat env content (Section__Key=value, Section__0__Key=value)
// into a nested JSON string. Numeric path segments produce arrays.
//
// With `comments`, the result is JSONC: a commented-out assignment becomes a
// `// "Key": value` line inside the deepest existing section of its path, and
// a plain comment becomes a `// text` line just before the entry that follows
// it. Without it, commented entries are left out and the result is strict JSON.
export const envToNestedJson = (
  env: string,
  { comments = false }: { comments?: boolean } = {},
): string => {
  const root: JsonContainer = {};
  const notes = new Map<JsonContainer, JsonNote[]>();
  let pending: string[] = [];
  const addNotes = (container: JsonContainer, texts: string[]): void => {
    if (texts.length === 0) return;
    const own = notes.get(container) ?? [];
    const after = memberCount(container);
    own.push(...texts.map((text) => ({ after, text })));
    notes.set(container, own);
  };
  const segmentOf = (part: string): string | number =>
    /^\d+$/.test(part) ? Number(part) : part;

  for (const entry of parseEnvFile(env)) {
    const { key, value, quoted, commented } = entry;
    if (commented) {
      if (!comments) continue;
      const marker = `//${(entry.prefix ?? DEFAULT_COMMENT_PREFIX).slice(1)}`;
      if (key === "") {
        pending.push(...value.split("\n").map((text) => marker + text));
        continue;
      }
      const parts = key.split("__");
      let container = root;
      let depth = 0;
      while (depth < parts.length - 1) {
        const next = container[segmentOf(parts[depth])];
        if (!isContainer(next)) break;
        container = next;
        depth++;
      }
      const rest = JSON.stringify(parts.slice(depth).join("__"));
      const scalar = JSON.stringify(coerceJsonValue(value, quoted));
      addNotes(container, [...pending, `${marker}${rest}: ${scalar}`]);
      pending = [];
      continue;
    }
    const parts = key.split("__");
    let cursor = root;
    for (let i = 0; i < parts.length; i++) {
      const segment = segmentOf(parts[i]);
      if (i === parts.length - 1) {
        addNotes(cursor, pending);
        pending = [];
        cursor[segment] = coerceJsonValue(value, quoted);
      } else {
        const nextIsIndex = /^\d+$/.test(parts[i + 1]);
        if (!isContainer(cursor[segment])) {
          cursor[segment] = nextIsIndex ? [] : {};
        }
        cursor = cursor[segment] as JsonContainer;
      }
    }
  }
  addNotes(root, pending);
  return printJson(root, notes);
};

const COMMENTED_JSON_MEMBER = /^("(?:[^"\\]|\\.)*")\s*:\s*(.+?)\s*,?$/;

// Convert a nested JSON string back to the flat .NET-style env representation.
// Accepts JSONC: `//` and `/* */` comments and trailing commas. A comment
// holding a single `"Key": scalar` member becomes a commented-out assignment
// under the section it sits in; any other comment becomes a plain comment.
export const nestedJsonToEnv = (json: string): string => {
  const lines: string[] = [];
  const len = json.length;
  let i = 0;

  const fail = (): never => {
    const line = json.substring(0, i).split("\n").length;
    throw new SyntaxError(
      i >= len
        ? "Unexpected end of JSON input"
        : `Unexpected token ${json[i]} in JSON on line ${line}`,
    );
  };

  const addComment = (body: string, path: string[]): void => {
    const [, spacing, text] = /^(#*[ \t]*)([^]*)$/.exec(body.trimEnd())!;
    const prefix = `#${spacing}`;
    const member = COMMENTED_JSON_MEMBER.exec(text);
    if (member) {
      try {
        const value: unknown = JSON.parse(member[2]);
        if (!isContainer(value)) {
          const key = [...path, JSON.parse(member[1]) as string].join("__");
          const scalar = formatScalar(value).replace(/\n/g, "\\n");
          lines.push(`${prefix}${key}=${scalar}`);
          return;
        }
      } catch {
        // Not a complete member; keep it as a plain comment.
      }
    }
    lines.push(`${prefix}${text}`.trimEnd());
  };

  const skipTrivia = (path: string[]): void => {
    while (i < len) {
      if (/\s/.test(json[i])) {
        i++;
      } else if (json.startsWith("//", i)) {
        const end = json.indexOf("\n", i);
        const stop = end === -1 ? len : end;
        addComment(json.substring(i + 2, stop), path);
        i = stop;
      } else if (json.startsWith("/*", i)) {
        const end = json.indexOf("*/", i + 2);
        if (end === -1) {
          i = len;
          fail();
        }
        for (const line of json.substring(i + 2, end).split("\n")) {
          const text = line.trim().replace(/^\*\s?/, "");
          if (text !== "") addComment(` ${text}`, path);
        }
        i = end + 2;
      } else {
        return;
      }
    }
  };

  const readString = (): string => {
    const start = i;
    i++;
    while (i < len && json[i] !== '"') {
      if (json[i] === "\n") fail();
      i += json[i] === "\\" ? 2 : 1;
    }
    if (i >= len) fail();
    i++;
    return JSON.parse(json.substring(start, i)) as string;
  };

  const readValue = (path: string[]): void => {
    skipTrivia(path.slice(0, -1));
    if (i >= len) fail();
    const ch = json[i];
    if (ch === "{" || ch === "[") {
      const close = ch === "{" ? "}" : "]";
      let index = 0;
      i++;
      for (;;) {
        skipTrivia(path);
        if (json[i] === close) break;
        if (ch === "{") {
          if (json[i] !== '"') fail();
          const key = readString();
          skipTrivia(path);
          if (json[i] !== ":") fail();
          i++;
          readValue([...path, key]);
        } else {
          readValue([...path, String(index++)]);
        }
        skipTrivia(path);
        if (json[i] === ",") i++;
        else if (json[i] !== close) fail();
      }
      i++;
      return;
    }
    let scalar: JsonScalar;
    if (ch === '"') {
      scalar = readString();
    } else {
      const literal =
        /^(?:-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null)/.exec(
          json.substring(i, i + 64),
        );
      if (!literal) return fail();
      i += literal[0].length;
      scalar = JSON.parse(literal[0]) as JsonScalar;
    }
    lines.push(`${path.join("__")}=${formatScalar(scalar)}`);
  };

  readValue([]);
  skipTrivia([]);
  if (i < len) fail();
  return lines.join("\n");
};

// Heuristic: does this set of keys look like an .NET-style nested config
// (i.e. uses `__` between segments to express hierarchy)?
export const keysLookNested = (keys: string[]): boolean =>
  keys.some((k) => /[A-Za-z0-9]__[A-Za-z0-9]/.test(k));
