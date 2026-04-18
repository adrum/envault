import "../css/app.css";

import { SessionExpirationNotification } from "@/components/session-expiration-notification";
import AppLayout from "@/layouts/app-layout";
import AuthLayout from "@/layouts/auth-layout";
import SettingsLayout from "@/layouts/settings/layout";
import theme from "@/theme";
import { createInertiaApp, type ResolvedComponent } from "@inertiajs/react";
import { configureEcho } from "@laravel/echo-react";
import { ColorSchemeScript, MantineProvider } from "@mantine/core";
import { ModalsProvider } from "@mantine/modals";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { Notifications } from "./components/notifications";

configureEcho({
  broadcaster: "reverb",
});

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: (name) => {
    switch (true) {
      case name === "welcome":
      case name === "setup":
        return null;
      case name.startsWith("auth/"):
        return AuthLayout;
      case name.startsWith("settings/"):
        return [AppLayout, SettingsLayout];
      default:
        return AppLayout;
    }
  },
  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.tsx`,
      import.meta.glob<ResolvedComponent>("./pages/**/*.tsx"),
    ),
  defaults: {
    visitOptions: (href, options) => {
      return { viewTransition: true };
    },
  },
  strictMode: true,
  withApp(app) {
    return (
      <>
        <ColorSchemeScript
          nonce="8IBTHwOdqNKAWeKl7plt8g=="
          defaultColorScheme="auto"
        />
        <MantineProvider defaultColorScheme="auto" theme={theme}>
          <ModalsProvider>
            <Notifications />
            <SessionExpirationNotification />
            {app}
          </ModalsProvider>
        </MantineProvider>
      </>
    );
  },
  progress: {
    color: "#6366f1",
  },
});
