import useHttpClient from "@/hooks/use-http-client";
import type { InertiaConfig } from "@inertiajs/core";
import { router } from "@inertiajs/react";
import { Button, Group, Text } from "@mantine/core";
import { notifications } from "@mantine/notifications";
import { useEffect, useRef, useState } from "react";

const NOTIFICATION_ID = "session-expiration";
const WARNING_SECONDS = 120;

function formatCountdown(seconds: number) {
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}:${s.toString().padStart(2, "0")}`;
}

function calcDrift(serverTime: number) {
  return Math.floor(Date.now() / 1000) - serverTime;
}

function serverExpiryToLocal(expiry: number, drift: number) {
  return expiry + drift;
}

type SharedData = InertiaConfig["sharedPageProps"];

let initialPageProps: SharedData = {} as unknown as SharedData;
try {
  initialPageProps =
    JSON.parse(document.getElementById("app")?.dataset.page || "{}").props ??
    ({} as SharedData);
} catch {
  // Ignore JSON parse errors
}

export function SessionExpirationNotification() {
  const initialDrift = calcDrift(
    initialPageProps.session?.server_time ?? Math.floor(Date.now() / 1000),
  );
  const initiallyAuthenticated = !!initialPageProps.auth?.user;

  const http = useHttpClient();
  const [authenticated, setAuthenticated] = useState(initiallyAuthenticated);
  const [remembered, setRemembered] = useState(
    initialPageProps.session?.remembered ?? false,
  );
  const [expiry, setExpiry] = useState<number | null>(
    initiallyAuthenticated ? (initialPageProps.session?.expiry ?? null) : null,
  );
  const driftRef = useRef(initialDrift);
  const warningTimerRef = useRef<ReturnType<typeof setTimeout>>(undefined);
  const expiryTimerRef = useRef<ReturnType<typeof setTimeout>>(undefined);
  const countdownRef = useRef<ReturnType<typeof setInterval>>(undefined);
  const expiryRef = useRef<number>(initialPageProps.session?.expiry ?? 0);

  useEffect(() => {
    const removeListener = router.on("navigate", (event) => {
      const props = event.detail.page.props as unknown as SharedData;
      const isAuthed = !!props.auth?.user;

      setAuthenticated(isAuthed);
      setRemembered(props.session?.remembered ?? false);

      if (isAuthed && props.session?.expiry && props.session?.server_time) {
        driftRef.current = calcDrift(props.session.server_time);
        expiryRef.current = props.session.expiry;
        setExpiry(props.session.expiry);
      }
    });

    return () => removeListener();
  }, []);

  useEffect(() => {
    if (!authenticated || !expiry || remembered) return;

    const doLogout = () => {
      notifications.hide(NOTIFICATION_ID);
      router.flushAll();
      router.post(
        "/logout",
        {},
        {
          onFinish: () => {
            window.location.href = "/login";
          },
        },
      );
    };

    const buildMessage = (remaining: number) => (
      <div>
        <Text size="sm" mb="xs">
          Your session expires in{" "}
          <Text span fw={700}>
            {formatCountdown(remaining)}
          </Text>
          . Would you like to extend it?
        </Text>
        <Group>
          <Button
            size="xs"
            onClick={() => {
              http
                .post("/session/extend")
                .then((res) => {
                  const newExpiry = res.headers["x-session-expiration"];
                  const serverTime = res.headers["x-server-time"];
                  if (newExpiry && serverTime) {
                    driftRef.current = calcDrift(parseInt(serverTime, 10));
                    expiryRef.current = parseInt(newExpiry, 10);
                  }
                  clearInterval(countdownRef.current);
                  notifications.hide(NOTIFICATION_ID);
                  schedule();
                })
                .catch(() => {
                  window.location.href = "/login";
                });
            }}
          >
            Extend Session
          </Button>
          <Button size="xs" variant="subtle" color="gray" onClick={doLogout}>
            Log Out
          </Button>
        </Group>
      </div>
    );

    const getRemainingSeconds = () => {
      const localExp = serverExpiryToLocal(expiryRef.current, driftRef.current);
      return Math.max(0, localExp - Math.floor(Date.now() / 1000));
    };

    const schedule = () => {
      clearTimeout(warningTimerRef.current);
      clearTimeout(expiryTimerRef.current);
      clearInterval(countdownRef.current);

      const localExpiry = serverExpiryToLocal(
        expiryRef.current,
        driftRef.current,
      );
      const now = Math.floor(Date.now() / 1000);
      const secondsUntilExpiry = localExpiry - now;
      const secondsUntilWarning = secondsUntilExpiry - WARNING_SECONDS;

      if (secondsUntilExpiry <= 0) {
        doLogout();
        return;
      }

      const showWarning = () => {
        const remaining = getRemainingSeconds();
        if (remaining <= 0) {
          doLogout();
          return;
        }

        notifications.show({
          id: NOTIFICATION_ID,
          title: "Session Expiring",
          message: buildMessage(remaining),
          autoClose: false,
          withCloseButton: false,
        });

        countdownRef.current = setInterval(() => {
          const secs = getRemainingSeconds();
          if (secs <= 0) {
            clearInterval(countdownRef.current);
            doLogout();
            return;
          }
          notifications.update({
            id: NOTIFICATION_ID,
            title: "Session Expiring",
            message: buildMessage(secs),
            autoClose: false,
            withCloseButton: false,
          });
        }, 1000);
      };

      if (secondsUntilWarning <= 0) {
        showWarning();
      } else {
        warningTimerRef.current = setTimeout(
          showWarning,
          secondsUntilWarning * 1000,
        );
      }

      expiryTimerRef.current = setTimeout(doLogout, secondsUntilExpiry * 1000);
    };

    schedule();

    return () => {
      clearTimeout(warningTimerRef.current);
      clearTimeout(expiryTimerRef.current);
      clearInterval(countdownRef.current);
    };
  }, [expiry, authenticated, remembered, http]);

  return null;
}
