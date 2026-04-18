export type Session =
  | {
      expiry?: number;
      remembered?: boolean;
      server_time?: number;
    }
  | undefined;
