import type { IconDefinition } from "@fortawesome/fontawesome-svg-core";
import type { InertiaLinkProps } from "@inertiajs/react";

export type BreadcrumbItem = {
  title: string;
  href: NonNullable<InertiaLinkProps["href"]>;
};

export type NavItem = {
  title: string;
  href: NonNullable<InertiaLinkProps["href"]>;
  icon?: IconDefinition | null;
  isActive?: boolean;
};
