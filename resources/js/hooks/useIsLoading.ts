import React, { useState } from "react";

export default function useIsLoading<T>() {
  const [state, setState] = useState<{ [key in keyof T]: number }>({} as any);
  React.useEffect(() => {
    const startEventListener = (event: any): void => {
      const only = (event.detail.visit.only || []) as (keyof T)[];
      only.forEach((key: keyof T) => {
        setState((prev: any) => ({
          ...prev,
          [key]: Math.max((prev[key] || 0) + 1, 0),
        }));
      });
    };

    const finishEventListener = (event: any) => {
      const only = (event.detail.visit.only || []) as (keyof T)[];
      only.forEach((key: keyof T) => {
        setState((prev: any) => ({
          ...prev,
          [key]: Math.max((prev[key] || 0) - 1, 0),
        }));
      });
    };

    document.addEventListener("inertia:start", startEventListener);
    document.addEventListener("inertia:finish", finishEventListener);
    return () => {
      document.removeEventListener("inertia:start", startEventListener);
      document.removeEventListener("inertia:finish", finishEventListener);
    };
  }, []);

  return Object.entries(state).reduce(
    (acc, [key, value]): { [key in keyof T]: boolean } => ({
      ...acc,
      [key]: (value as number) > 0,
    }),
    {} as { [key in keyof T]: boolean },
  );
}
