import AppLogoIcon from "@/components/app-logo-icon";
import { useMobileNavigation } from "@/hooks/use-mobile-navigation";
import type { BreadcrumbItem } from "@/types/navigation";
import {
  faBoxesStacked,
  faClipboardList,
  faUserCircle,
  faUsers,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Link, router, usePage } from "@inertiajs/react";
import {
  AppShell,
  Burger,
  Container,
  Group,
  Menu,
  Text,
  UnstyledButton,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import type { PropsWithChildren } from "react";

type NavItem = {
  label: string;
  href: string;
  icon: typeof faBoxesStacked;
};

function NavLink({
  item,
  active,
  onClick,
}: {
  item: NavItem;
  active: boolean;
  onClick?: () => void;
}) {
  return (
    <Link
      href={item.href}
      onClick={onClick}
      className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
        active
          ? "bg-gray-900 text-white"
          : "text-gray-300 hover:bg-gray-700 hover:text-white"
      }`}
    >
      <FontAwesomeIcon icon={item.icon} className="size-4" />
      <span>{item.label}</span>
    </Link>
  );
}

export default function AppFancyLayout({
  children,
  breadcrumbs = [],
  title,
  headerAction,
}: PropsWithChildren<{
  breadcrumbs?: BreadcrumbItem[];
  title?: string;
  headerAction?: React.ReactNode;
}>) {
  const [opened, { toggle, close }] = useDisclosure();
  const { auth, can } = usePage().props;
  const cleanup = useMobileNavigation();
  const pathname =
    typeof window !== "undefined" ? window.location.pathname : "";

  const navItems: NavItem[] = [
    { label: "Apps", href: "/apps", icon: faBoxesStacked },
    ...(can.administrate
      ? [
          { label: "Audit Log", href: "/log", icon: faClipboardList },
          { label: "Users", href: "/users", icon: faUsers },
        ]
      : []),
  ];

  const handleLogout = () => {
    cleanup();
    router.flushAll();
    router.post("/logout");
  };

  // Derive page title from breadcrumbs if not explicitly passed
  const pageTitle = title || breadcrumbs[breadcrumbs.length - 1]?.title;

  return (
    <AppShell
      header={{ height: 64 }}
      navbar={{
        width: 260,
        breakpoint: "md",
        collapsed: { desktop: true, mobile: !opened },
      }}
      className="bg-gray-100! dark:bg-gray-950!"
    >
      {/* Dark top navigation bar */}
      <AppShell.Header className="border-b border-gray-700 bg-gray-800!">
        <Container size="lg" h="100%">
          <Group h="100%" justify="space-between" wrap="nowrap">
            <Group gap="md" wrap="nowrap">
              <Link href="/apps" className="flex items-center">
                <AppLogoIcon
                  className="size-8"
                  style={{
                    filter:
                      "brightness(0) saturate(100%) invert(100%) sepia(0%) saturate(0%) hue-rotate(0deg)",
                  }}
                />
              </Link>

              <Group gap={4} visibleFrom="md">
                {navItems.map((item) => (
                  <NavLink
                    key={item.href}
                    item={item}
                    active={pathname.startsWith(item.href)}
                  />
                ))}
              </Group>
            </Group>

            <Group gap="sm" wrap="nowrap">
              <Menu shadow="md" width={220} position="bottom-end">
                <Menu.Target>
                  <UnstyledButton
                    className="hidden items-center md:flex"
                    aria-label="User menu"
                  >
                    <FontAwesomeIcon
                      icon={faUserCircle}
                      className="text-2xl! text-white"
                    />
                  </UnstyledButton>
                </Menu.Target>
                <Menu.Dropdown>
                  <Menu.Label>
                    <Text size="sm" fw={500}>
                      {auth.user?.name}
                    </Text>
                    <Text size="xs" c="dimmed">
                      {auth.user?.email}
                    </Text>
                  </Menu.Label>
                  <Menu.Divider />
                  <Menu.Item component={"a"} href="/docs">
                    Documentation
                  </Menu.Item>
                  <Menu.Item component={Link} href="/settings/profile">
                    My Account
                  </Menu.Item>
                  <Menu.Item onClick={handleLogout}>Sign out</Menu.Item>
                </Menu.Dropdown>
              </Menu>

              <Burger
                opened={opened}
                onClick={toggle}
                hiddenFrom="md"
                size="sm"
                color="white"
              />
            </Group>
          </Group>
        </Container>
      </AppShell.Header>

      {/* Mobile navbar */}
      <AppShell.Navbar className="bg-gray-800! p-4">
        <div className="flex flex-col gap-1">
          {navItems.map((item) => (
            <NavLink
              key={item.href}
              item={item}
              active={pathname.startsWith(item.href)}
              onClick={close}
            />
          ))}
        </div>
        <div className="mt-4 border-t border-gray-700 pt-4">
          <div className="px-3 pb-3">
            <Text size="sm" fw={500} c="white">
              {auth.user?.name}
            </Text>
            <Text size="xs" c="gray.4">
              {auth.user?.email}
            </Text>
          </div>
          <Link
            href="/settings/profile"
            onClick={close}
            className="block rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white"
          >
            My Account
          </Link>
          <button
            onClick={handleLogout}
            className="block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-gray-400 hover:bg-gray-700 hover:text-white"
          >
            Sign out
          </button>
        </div>
      </AppShell.Navbar>

      <AppShell.Main>
        {/* Impersonation banner */}
        {(usePage().props as any).impersonating && (
          <div className="bg-yellow-500 px-4 py-2 text-center text-sm font-medium text-yellow-950">
            You are impersonating {auth.user?.name}.{" "}
            <button
              onClick={() => router.post("/impersonate-stop")}
              className="cursor-pointer underline underline-offset-2 hover:no-underline"
            >
              Stop impersonating
            </button>
          </div>
        )}

        {/* Dark header bleed with page title */}
        <div className="bg-gray-800 pb-56">
          {pageTitle && (
            <Container size="md" className="px-4 pt-10 sm:px-6">
              <div className="flex items-center justify-between">
                <h1 className="text-3xl leading-9 font-bold text-white">
                  {pageTitle}
                </h1>
                {headerAction && <div>{headerAction}</div>}
              </div>
            </Container>
          )}
        </div>

        {/* Content pulled up over the dark area */}
        <Container size="md" className="-mt-44 px-4 pb-12 sm:px-6">
          {children}
        </Container>

        {/* Footer */}
        <Container size="md" className="px-4 py-6 sm:px-6">
          <Text size="sm" c="dimmed">
            &copy; Envault {new Date().getFullYear()}
          </Text>
        </Container>
      </AppShell.Main>
    </AppShell>
  );
}
