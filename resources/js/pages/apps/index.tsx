import {
  faArrowRight,
  faBoxesStacked,
  faChevronRight,
  faMagnifyingGlass,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { Button, Text, TextInput } from "@mantine/core";
import { useState } from "react";

type App = {
  id: number;
  name: string;
  variables_count: number;
};

type PaginatedApps = {
  data: App[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  links: { url: string | null; label: string; active: boolean }[];
};

export default function AppsIndex({
  apps,
  search: initialSearch,
}: {
  apps: PaginatedApps;
  search: string;
}) {
  const { can } = usePage().props as any;
  const [search, setSearch] = useState(initialSearch || "");
  const [newAppName, setNewAppName] = useState("");
  const [creating, setCreating] = useState(false);

  const handleSearch = (value: string) => {
    setSearch(value);
    router.get(
      "/apps",
      { search: value || undefined },
      { preserveState: true, replace: true },
    );
  };

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAppName.trim()) return;
    setCreating(true);
    router.post(
      "/apps",
      { name: newAppName },
      {
        onSuccess: () => {
          setNewAppName("");
          setCreating(false);
        },
        onError: () => setCreating(false),
      },
    );
  };

  return (
    <>
      <Head title="Apps" />

      {(apps.total > 0 || search) && (
        <div className="overflow-hidden rounded-md bg-background shadow">
          {/* Search bar */}
          <div className="border-b border-gray-200 px-4 py-5 sm:px-6 dark:border-gray-700">
            <div className="flex items-center justify-end">
              <TextInput
                placeholder="Search..."
                leftSection={
                  <FontAwesomeIcon
                    icon={faMagnifyingGlass}
                    className="size-4 text-gray-400"
                  />
                }
                value={search}
                onChange={(e) => handleSearch(e.currentTarget.value)}
                classNames={{
                  input:
                    "border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500",
                }}
              />
            </div>
          </div>

          {/* App list */}
          <ul>
            {apps.data.length > 0 ? (
              apps.data.map((app, index) => (
                <li
                  key={app.id}
                  className={
                    index > 0
                      ? "border-t border-gray-200 dark:border-gray-700"
                      : ""
                  }
                >
                  <Link
                    href={`/apps/${app.id}`}
                    className="block transition-colors duration-150 hover:bg-gray-50 focus:bg-gray-100 focus:outline-none dark:hover:bg-gray-800"
                  >
                    <div className="flex items-center px-4 py-4 sm:px-6">
                      <div className="min-w-0 flex-1">
                        <Text
                          size="sm"
                          fw={500}
                          className="truncate text-primary!"
                        >
                          {app.name}
                        </Text>
                        <div className="mt-2 flex items-center">
                          <FontAwesomeIcon
                            icon={faBoxesStacked}
                            className="mr-1.5 size-4 shrink-0 text-gray-400"
                          />
                          <Text size="sm" c="dimmed" className="truncate">
                            {app.variables_count}{" "}
                            {app.variables_count === 1
                              ? "variable"
                              : "variables"}
                          </Text>
                        </div>
                      </div>
                      <FontAwesomeIcon
                        icon={faChevronRight}
                        className="size-4 text-gray-400"
                      />
                    </div>
                  </Link>
                </li>
              ))
            ) : (
              <li className="px-4 py-4 sm:px-6">
                <Text size="sm" fw={500} c="dimmed">
                  No results match this query.
                </Text>
              </li>
            )}
          </ul>

          {/* Pagination */}
          {apps.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-6 dark:border-gray-700">
              <Text size="sm" c="dimmed">
                Showing {apps.from} to {apps.to} of {apps.total} results
              </Text>
              <div className="flex gap-2">
                {apps.links
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
      )}

      {/* No apps warning for non-admins */}
      {!apps.total && !search && !can.administrate && (
        <div className="rounded-md bg-yellow-50 p-4 shadow dark:bg-yellow-900/20">
          <div className="flex">
            <div className="ml-3">
              <h3 className="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                No apps
              </h3>
              <div className="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                <p>
                  There are currently no apps here. Please ask an administrator
                  to create one for you.
                </p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* New app form (inline, for admins) */}
      {can.administrate && (
        <div className={apps.total > 0 || search ? "mt-6" : ""}>
          <div className="overflow-hidden rounded-md bg-background shadow">
            <div className="px-4 py-5 sm:px-6">
              <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
                New app
              </h3>
            </div>
            <div className="border-t border-gray-200 px-4 py-5 sm:px-6 dark:border-gray-700">
              <form onSubmit={handleCreate}>
                <div className="flex items-end gap-4">
                  <TextInput
                    placeholder="My Awesome App"
                    value={newAppName}
                    onChange={(e) => setNewAppName(e.currentTarget.value)}
                    className="flex-1"
                  />
                  <Button
                    type="submit"
                    loading={creating}
                    disabled={!newAppName.trim()}
                    rightSection={
                      <FontAwesomeIcon icon={faArrowRight} className="size-4" />
                    }
                  >
                    Continue
                  </Button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

AppsIndex.layout = {
  breadcrumbs: [{ title: "Apps", href: "/apps" }],
};
