import inertia from "@inertiajs/vite";
import { wayfinder } from "@laravel/vite-plugin-wayfinder";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.tsx"],
      ssr: "resources/js/ssr.tsx",
      refresh: true,
    }),
    inertia({
      ssr: false,
    }),
    react(),
    tailwindcss(),
    !process.env.CI &&
    !process.env.DOCKER &&
    wayfinder({
      path: "resources/js/wayfinder",
      formVariants: true,
      command: process.env.CI
        ? undefined
        : "herd php artisan typescript:all; herd php artisan wayfinder:generate",
    }),
  ],
});
