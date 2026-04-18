import { cn } from "@/lib/utils";
import type { IconDefinition } from "@fortawesome/fontawesome-svg-core";
import { faDesktop, faMoon, faSun } from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import type { MantineColorScheme } from "@mantine/core";
import { useMantineColorScheme } from "@mantine/core";
import type { HTMLAttributes } from "react";

export default function AppearanceToggleTab({
  className = "",
  ...props
}: HTMLAttributes<HTMLDivElement>) {
  const { colorScheme, setColorScheme } = useMantineColorScheme();

  const tabs: {
    value: MantineColorScheme;
    icon: IconDefinition;
    label: string;
  }[] = [
    { value: "light", icon: faSun, label: "Light" },
    { value: "dark", icon: faMoon, label: "Dark" },
    { value: "auto", icon: faDesktop, label: "System" },
  ];

  return (
    <div
      className={cn(
        "inline-flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800",
        className,
      )}
      {...props}
    >
      {tabs.map(({ value, icon, label }) => (
        <button
          key={value}
          onClick={() => setColorScheme(value)}
          className={cn(
            "flex items-center rounded-md px-3.5 py-1.5 transition-colors",
            colorScheme === value
              ? "bg-white shadow-xs dark:bg-gray-700 dark:text-gray-100"
              : "text-gray-500 hover:bg-gray-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60",
          )}
        >
          <FontAwesomeIcon icon={icon} className="-ml-1 h-4 w-4" />
          <span className="ml-1.5 text-sm">{label}</span>
        </button>
      ))}
    </div>
  );
}
