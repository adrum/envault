import { Visit, router } from "@inertiajs/core";
import React from "react";

export default function usePolling(
  timeout: number | boolean,
  only: string[] | undefined = undefined,
) {
  React.useEffect(() => {
    if (!timeout || timeout === true) return;

    const options: Partial<Visit> = {
      preserveState: true,
    };

    if (only) {
      options.only = only;
    }

    const id = setInterval(() => router.reload(options), timeout);
    return () => {
      if (id) {
        clearInterval(id);
      }
    };
  }, [timeout, only]);
}
