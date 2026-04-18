import { Head, router } from "@inertiajs/react";
import { Button, Select, Text } from "@mantine/core";
import { useState } from "react";

type LogEntryData = {
  id: number;
  action: string;
  description: string;
  user: { id: number; first_name: string; last_name: string } | null;
  created_at: string;
};

type PaginatedEntries = {
  data: LogEntryData[];
  current_page: number;
  last_page: number;
  from: number;
  to: number;
  total: number;
  links: { url: string | null; label: string; active: boolean }[];
};

type FilterData = {
  action?: string;
  app?: string;
  user?: string;
};

const actionData = [
  {
    group: "Users",
    items: [
      { value: "created", label: "User added" },
      { value: "deleted", label: "User removed" },
      { value: "signed_in", label: "User signed in" },
      { value: "updated_email", label: "User email address updated" },
      { value: "updated_name", label: "User name updated" },
      { value: "updated_role", label: "User role updated" },
    ],
  },
  {
    group: "Apps",
    items: [
      { value: "app_created", label: "App created" },
      { value: "app_deleted", label: "App deleted" },
      { value: "app_updated_name", label: "App name updated" },
      { value: "updated_notifications", label: "App notifications set up" },
      {
        value: "notification_settings_updated",
        label: "App notification settings updated",
      },
      { value: "added_collaborator", label: "Collaborator added" },
      { value: "removed_collaborator", label: "Collaborator removed" },
      {
        value: "updated_collaborator_role",
        label: "Collaborator role updated",
      },
    ],
  },
  {
    group: "Variables",
    items: [
      { value: "var_created", label: "Variable created" },
      { value: "imported", label: "Variables imported" },
      { value: "var_deleted", label: "Variable deleted" },
      { value: "updated_key", label: "Variable key updated" },
      { value: "updated_value", label: "Variable value updated" },
      { value: "rolled_back", label: "Variable rolled back" },
    ],
  },
];

function formatDate(dateStr: string): string {
  const d = new Date(dateStr);
  return d.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
}

export default function LogIndex({
  entries,
  filters,
  apps,
  users,
}: {
  entries: PaginatedEntries;
  filters: FilterData;
  actions: string[];
  apps: Record<string, string>;
  users: { id: number; first_name: string; last_name: string }[];
}) {
  const [currentFilters, setCurrentFilters] = useState(filters);

  const updateFilter = (key: string, value: string | null) => {
    const newFilters = { ...currentFilters, [key]: value || undefined };
    setCurrentFilters(newFilters);
    router.get(
      "/log",
      Object.fromEntries(Object.entries(newFilters).filter(([_, v]) => v)),
      { preserveState: true, replace: true },
    );
  };

  const appOptions = Object.entries(apps || {}).map(([id, name]) => ({
    value: id,
    label: name as string,
  }));

  const userOptions = (users || []).map((u) => ({
    value: String(u.id),
    label: `${u.first_name} ${u.last_name}`,
  }));

  return (
    <>
      <Head title="Audit Log" />

      <div className="overflow-hidden rounded-md bg-background shadow">
        {/* Filters */}
        <div className="grid grid-cols-3 gap-3 border-b border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
          <Select
            placeholder="Select action..."
            data={actionData}
            value={currentFilters.action || null}
            onChange={(v) => updateFilter("action", v)}
            clearable
            searchable
          />
          <Select
            placeholder="Select app..."
            data={appOptions}
            value={currentFilters.app || null}
            onChange={(v) => updateFilter("app", v)}
            clearable
            searchable
          />
          <Select
            placeholder="Select user responsible..."
            data={userOptions}
            value={currentFilters.user || null}
            onChange={(v) => updateFilter("user", v)}
            clearable
            searchable
          />
        </div>

        {/* Table */}
        {entries.data.length > 0 ? (
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 dark:border-gray-700">
                <th className="px-4 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase sm:px-6">
                  User
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase sm:px-6">
                  Description
                </th>
                <th className="px-4 py-3 text-right text-xs font-semibold tracking-wider text-gray-500 uppercase sm:px-6">
                  Date
                </th>
              </tr>
            </thead>
            <tbody>
              {entries.data.map((entry) => (
                <tr
                  key={entry.id}
                  className="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                >
                  <td className="px-4 py-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:px-6 dark:text-gray-100">
                    {entry.user
                      ? `${entry.user.first_name} ${entry.user.last_name}`
                      : "System"}
                  </td>
                  <td className="px-4 py-4 text-sm text-gray-500 sm:px-6 dark:text-gray-400">
                    {entry.description}
                  </td>
                  <td className="px-4 py-4 text-right text-sm whitespace-nowrap text-gray-500 sm:px-6 dark:text-gray-400">
                    {formatDate(entry.created_at)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <div className="px-4 py-8 text-center sm:px-6">
            <Text size="sm" c="dimmed">
              No log entries found.
            </Text>
          </div>
        )}

        {/* Pagination */}
        {entries.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-6 dark:border-gray-700">
            <Text size="sm" c="dimmed">
              Showing {entries.from} to {entries.to} of {entries.total} results
            </Text>
            <div className="flex gap-2">
              {entries.links
                .filter(
                  (link) =>
                    link.label === "&laquo; Previous" ||
                    link.label === "Next &raquo;",
                )
                .map((link) => {
                  const label = link.label.includes("Previous")
                    ? "Previous"
                    : "Next";
                  return (
                    <Button
                      key={label}
                      variant="default"
                      size="xs"
                      disabled={!link.url}
                      onClick={() =>
                        link.url &&
                        router.get(link.url, {}, { preserveState: true })
                      }
                    >
                      {label}
                    </Button>
                  );
                })}
            </div>
          </div>
        )}
      </div>
    </>
  );
}

LogIndex.layout = {
  breadcrumbs: [{ title: "Audit Log", href: "/log" }],
};
