import type { Auth } from "@/types/auth";
import type { FlashToast } from "@/types/ui";
import "@inertiajs/core";
import type { Session } from "./session";

declare module "@inertiajs/core" {
  export interface InertiaConfig {
    sharedPageProps: {
      name: string;
      auth: Auth;
      session: Session;
      query: Record<string, string | undefined>;
      sidebarOpen: boolean;
      can: {
        administrate: boolean;
      };
      features: {
        jsonMode: boolean;
      };
      flash: {
        newToken?: string;
      };
      [key: string]: unknown;
    };
  }
}
