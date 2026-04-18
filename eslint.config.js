import js from "@eslint/js";
import prettier from "eslint-config-prettier/flat";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import unusedImports from "eslint-plugin-unused-imports";
import globals from "globals";
import typescript from "typescript-eslint";

/** @type {import('eslint').Linter.Config[]} */
export default [
  js.configs.recommended,
  ...typescript.configs.recommended,
  {
    ...react.configs.flat.recommended,
    ...react.configs.flat["jsx-runtime"], // Required for React 17+
    languageOptions: {
      globals: {
        ...globals.browser,
      },
    },
    rules: {
      "react/react-in-jsx-scope": "off",
      "react/prop-types": "off",
      "react/no-unescaped-entities": "off",
    },
    settings: {
      react: {
        version: "detect",
      },
    },
    ignores: [
      "resources/js/generated.d.ts",
      "resources/js/enums.ts",
      "resources/js/wayfinder",
      "**/node_modules/**/*",
      "**/public/**/*",
      "**/vendor/**/*",
    ],
  },
  {
    plugins: {
      "react-hooks": reactHooks,
      "unused-imports": unusedImports,
    },
    rules: {
      "react-hooks/rules-of-hooks": "error",
      "react-hooks/exhaustive-deps": "warn",

      "no-unused-expressions": 1,
      "react/prop-types": "off",
      "react/react-in-jsx-scope": "off",
      "no-unused-vars": "off",

      "no-multiple-empty-lines": [
        "error",
        {
          max: 1,
          maxBOF: 0,
          maxEOF: 0,
        },
      ],

      "unused-imports/no-unused-imports": "error",
      "@typescript-eslint/no-unused-vars": "off",

      "@typescript-eslint/no-explicit-any": "off",
    },
  },
  {
    ignores: [
      "vendor",
      "node_modules",
      "public",
      "bootstrap/ssr",
      "tailwind.config.js",
    ],
  },
  prettier, // Turn off all rules that might conflict with Prettier
];
