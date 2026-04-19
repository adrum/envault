import Heading from "@/components/heading";
import { useCurrentUrl } from "@/hooks/use-current-url";
import { toUrl } from "@/lib/utils";
import type { NavItem } from "@/types";
import { edit as editAppearance } from "@/wayfinder/routes/appearance";
import { edit } from "@/wayfinder/routes/profile";
import { edit as editSecurity } from "@/wayfinder/routes/security";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Link, usePage } from "@inertiajs/react";
import { Button, Text } from "@mantine/core";
import type { PropsWithChildren } from "react";
import { useMemo } from "react";

type NavSection = {
  label: string;
  items: NavItem[];
};

export default function SettingsLayout({ children }: PropsWithChildren) {
  const { isCurrentOrParentUrl } = useCurrentUrl();
  const { can } = usePage().props as any;

  const navSections = useMemo<NavSection[]>(() => {
    const sections: NavSection[] = [
      {
        label: "Personal",
        items: [
          { title: "Profile", href: edit(), icon: null },
          { title: "Security", href: editSecurity(), icon: null },
          { title: "Appearance", href: editAppearance(), icon: null },
          { title: "API Tokens", href: "/settings/tokens", icon: null },
        ],
      },
    ];

    if (can?.administrate) {
      sections.push({
        label: "Global",
        items: [
          { title: "Environments", href: "/settings/environments", icon: null },
        ],
      });
    }

    return sections;
  }, [can]);

  return (
    <div className="rounded-lg border border-border bg-background px-4 py-6">
      <Heading
        title="Settings"
        description="Manage your profile and account settings"
      />

      <div className="flex flex-col lg:flex-row lg:space-x-12">
        <aside className="w-full max-w-xl lg:w-48">
          <nav
            className="flex flex-col space-y-1 space-x-0"
            aria-label="Settings"
          >
            {navSections.map((section, sIndex) => (
              <div
                key={section.label}
                className={`flex flex-col space-y-1${sIndex > 0 ? "pt-4" : ""}`}
              >
                <Text
                  size="xs"
                  fw={600}
                  c="dimmed"
                  className="mb-1 px-3 tracking-wider uppercase"
                >
                  {section.label}
                </Text>
                {section.items.map((item, index) => (
                  <Button
                    key={`${toUrl(item.href)}-${index}`}
                    href={toUrl(item.href)}
                    component={Link}
                    prefetch
                    size="sm"
                    justify="start"
                    color="gray"
                    variant="subtle"
                    leftSection={
                      item.icon && (
                        <FontAwesomeIcon icon={item.icon} className="h-4 w-4" />
                      )
                    }
                    styles={{
                      root: {
                        ...(isCurrentOrParentUrl(toUrl(item.href)) && {
                          backgroundColor: "var(--color-muted)",
                        }),
                      },
                    }}
                  >
                    {item.title}
                  </Button>
                ))}
              </div>
            ))}
          </nav>
        </aside>

        <div className="my-6 border-2 bg-border lg:hidden" />

        <div className="flex-1 md:max-w-2xl">
          <section className="max-w-xl space-y-12">{children}</section>
        </div>
      </div>
    </div>
  );
}
