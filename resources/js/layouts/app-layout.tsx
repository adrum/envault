import AppLayoutTemplate from "@/layouts/app/app-fancy-layout";
import type { BreadcrumbItem } from "@/types";

export default function AppLayout({
  breadcrumbs = [],
  headerAction,
  children,
}: {
  breadcrumbs?: BreadcrumbItem[];
  headerAction?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <AppLayoutTemplate breadcrumbs={breadcrumbs} headerAction={headerAction}>
      {children}
    </AppLayoutTemplate>
  );
}
