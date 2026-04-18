import {
  faCheckCircle,
  faXmarkCircle,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { router } from "@inertiajs/react";
import {
  Notifications as MantineNotifications,
  notifications,
} from "@mantine/notifications";

router.on("flash", (event) => {
  if (event.detail.flash.success) {
    notifications.show({
      title: "Success",
      icon: <FontAwesomeIcon icon={faCheckCircle} color="white" />,
      message: <p>{(event.detail.flash.success as string) ?? ""}</p>,
      autoClose: true,
      withCloseButton: true,
    });
  }
  if (event.detail.flash.error) {
    notifications.show({
      title: "Error",
      icon: <FontAwesomeIcon icon={faXmarkCircle} color="white" />,
      message: <p>{(event.detail.flash.error as string) ?? ""}</p>,
      autoClose: true,
      withCloseButton: true,
    });
  }
});

export function Notifications() {
  return <MantineNotifications position="top-right" />;
}
