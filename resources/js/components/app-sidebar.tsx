import { useIsMobile } from "@/hooks/use-mobile";
import { cn } from "@/lib/utils";
import type { NavItem } from "@/types";
import type { SharedProps } from "@/types/auth";
import {
  faBoxesStacked,
  faClipboardList,
  faUsers,
  faXmark,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Link, usePage } from "@inertiajs/react";
import { Button } from "@mantine/core";

const InertiaLink: React.ElementType = Link;

import AppLogo from "./app-logo";
import { NavUser } from "./nav-user";
import SidebarMenuButton from "./sidebar-menu-button";

const SidebarGroupLabel = ({ children }: { children: React.ReactNode }) => (
  <div
    className={cn(
      "flex h-8 shrink-0 items-center rounded-md px-2 text-xs font-medium text-sidebar-foreground/70 ring-sidebar-ring outline-hidden transition-[margin,opacity] duration-200 ease-linear focus-visible:ring-2 [&>svg]:size-4 [&>svg]:shrink-0",
    )}
  >
    {children}
  </div>
);

export function AppSidebar({
  collapsed,
  className,
  toggle,
}: {
  collapsed: boolean;
  className?: string;
  toggle: () => void;
}) {
  const pathname =
    typeof window !== "undefined" ? window.location.pathname : "";
  const isMobile = useIsMobile();
  const { can } = usePage<{ props: SharedProps }>()
    .props as unknown as SharedProps;

  const mainNavItems: NavItem[] = [
    {
      title: "Apps",
      href: "/apps",
      icon: faBoxesStacked,
    },
    ...(can.administrate
      ? [
          {
            title: "Audit Log",
            href: "/log",
            icon: faClipboardList,
          },
          {
            title: "Users",
            href: "/users",
            icon: faUsers,
          },
        ]
      : []),
  ];

  return (
    <div
      data-collapsible={collapsed ? "icon" : ""}
      className={cn(
        "flex h-full flex-col bg-sidebar text-sidebar-foreground",
        collapsed && "items-center",
        className,
      )}
    >
      <div
        className={cn(
          "flex items-center justify-center px-4 pt-4",
          collapsed ? "mb-2 px-1.5" : "pb-4",
        )}
      >
        <SidebarMenuButton
          component={InertiaLink}
          href="/apps"
          prefetch
          icon={<AppLogo showName={!collapsed} />}
          iconOnly={collapsed}
          className="h-12! flex-1"
        />
        {isMobile && (
          <Button
            onClick={toggle}
            variant="icon"
            className="bg-transparent! p-0! px-2! hover:bg-muted!"
            aria-label="Close sidebar"
          >
            <FontAwesomeIcon
              icon={faXmark}
              className="size-6"
              color="var(--sidebar-foreground)"
            />
          </Button>
        )}
      </div>
      <div
        id="main-nav"
        className={cn(
          "flex flex-1 flex-col gap-y-2",
          collapsed ? "items-center" : "items-stretch justify-start px-4",
        )}
      >
        {!collapsed && <SidebarGroupLabel>Platform</SidebarGroupLabel>}
        {mainNavItems.map((item) => (
          <SidebarMenuButton
            key={item.title}
            href={typeof item.href === "string" ? item.href : item.href.url}
            tooltip={item.title}
            size="sm"
            className={cn("font-medium!")}
            isActive={
              typeof item.href === "string"
                ? pathname.startsWith(item.href)
                : pathname.startsWith(item.href.url)
            }
            icon={
              item.icon && (
                <FontAwesomeIcon icon={item.icon} className="size-4" />
              )
            }
            iconOnly={collapsed}
          >
            {item.title}
          </SidebarMenuButton>
        ))}
      </div>
      <div id="footer-nav">
        <div className="flex flex-1 flex-col items-stretch justify-start p-4">
          <NavUser />
        </div>
      </div>
    </div>
  );
}
