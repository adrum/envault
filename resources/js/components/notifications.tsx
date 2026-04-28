import type { FlashToast } from "@/types/ui";
import {
  faCheckCircle,
  faInfoCircle,
  faTriangleExclamation,
  faXmarkCircle,
  type IconDefinition,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { router } from "@inertiajs/react";
import {
  Notifications as MantineNotifications,
  notifications,
} from "@mantine/notifications";

router.on("flash", (event) => {
  const flash = (event as CustomEvent).detail?.flash;
  const data = flash?.toast as FlashToast | undefined;

  if (!data || !data.message) {
    return;
  }

  let title = "Success";
  let icon: IconDefinition = faCheckCircle;

  switch (data.type) {
    case "info":
      title = "Info";
      icon = faInfoCircle;
      break;
    case "error":
      title = "Error";
      icon = faXmarkCircle;
      break;
    case "warning":
      title = "Warning";
      icon = faTriangleExclamation;
      break;
  }

  notifications.show({
    title,
    icon: <FontAwesomeIcon icon={icon} color="white" />,
    message: <p>{data.message ?? ""}</p>,
    autoClose: true,
    withCloseButton: true,
  });
});

export function Notifications() {
  return <MantineNotifications position="top-right" />;
}
