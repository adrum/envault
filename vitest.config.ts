import { fileURLToPath } from "node:url";
import { defineConfig } from "vitest/config";

// Standalone from vite.config.ts on purpose: the build config loads the Laravel
// and Wayfinder plugins, which shell out to artisan and are not wanted here.
export default defineConfig({
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./resources/js", import.meta.url)),
    },
  },
  test: {
    include: ["resources/js/**/*.test.ts"],
    environment: "node",
  },
});
