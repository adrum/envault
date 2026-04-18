import { faGear, faRightFromBracket } from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Link, router } from "@inertiajs/react";
import { Menu } from "@mantine/core";

import { UserInfo } from "@/components/user-info";
import { useMobileNavigation } from "@/hooks/use-mobile-navigation";
import type { User } from "@/types";
import { logout } from "@/wayfinder/routes";
import { edit } from "@/wayfinder/routes/profile";

interface UserMenuContentProps {
  user: User;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
  const cleanup = useMobileNavigation();

  const handleLogout = () => {
    cleanup();
    router.flushAll();
    router.post(logout());
  };

  return (
    <>
      <Menu.Dropdown className="border-2 border-border">
        <Menu.Label>
          <UserInfo user={user} showEmail={true} />
        </Menu.Label>

        <Menu.Divider />

        <Menu.Item
          component={Link}
          href={edit()}
          className="block w-full cursor-pointer"
          leftSection={
            <FontAwesomeIcon icon={faGear} className="size-5" color="gray" />
          }
        >
          Settings
        </Menu.Item>
        <Menu.Divider />
        <form
          onSubmit={(e) => {
            e.preventDefault();
            handleLogout();
          }}
        >
          <Menu.Item
            className="block w-full cursor-pointer"
            leftSection={
              <FontAwesomeIcon icon={faRightFromBracket} color="gray" />
            }
            type="submit"
            data-test="logout-button"
          >
            Log Out
          </Menu.Item>
        </form>
      </Menu.Dropdown>
    </>
  );
}
