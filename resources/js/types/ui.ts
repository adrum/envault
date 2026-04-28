import type { BreadcrumbItem } from "@/types/navigation";
import type { ReactNode } from "react";

export type AppLayoutProps = {
  title?: string;
  navbarSelected?: boolean;
  children: ReactNode;
  breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = "header" | "sidebar";

export type FlashToast = {
  type: App.Enums.FlashToastType;
  message: string;
};

export type AuthLayoutProps = {
  children?: ReactNode;
  name?: string;
  title?: string;
  description?: string;
};
