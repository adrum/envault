import type { Auth } from "@/types/auth";
import "@inertiajs/core";
import type { Session } from "./session";

declare module "@inertiajs/core" {
  export interface InertiaConfig {
    sharedPageProps: {
      name: string;
      auth: Auth;
      session: Session;
      sidebarOpen: boolean;
      can: {
        administrate: boolean;
      };
      flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
      };
    };
  }
}
