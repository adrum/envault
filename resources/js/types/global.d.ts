import type { Auth } from "@/types/auth";
import type { Session } from "./session";

declare module "@inertiajs/core" {
  export interface InertiaConfig {
    sharedPageProps: {
      name: string;
      auth: Auth;
      session: Session;
      sidebarOpen: boolean;
      [key: string]: unknown;
    };
  }
}
