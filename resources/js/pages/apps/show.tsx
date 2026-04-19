import { AppColor } from "@/colors";
import { StreamLanguage } from "@codemirror/language";
import { oneDark } from "@codemirror/theme-one-dark";
import {
  faCheck,
  faChevronLeft,
  faChevronRight,
  faClipboard,
  faPencil,
  faRotateLeft,
  faTrash,
  faUpload,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Head, Link, router, setLayoutProps } from "@inertiajs/react";
import {
  ActionIcon,
  Badge,
  Button,
  Code,
  CopyButton,
  Group,
  Modal,
  Select,
  Stack,
  Text,
  Textarea,
  TextInput,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import CodeMirror from "@uiw/react-codemirror";
import { useEffect, useRef, useState } from "react";

// Simple .env syntax highlighting
const envLanguage = StreamLanguage.define({
  token(stream) {
    if (stream.sol() && stream.match(/^#.*/)) {
      return "comment";
    }
    if (stream.sol() && stream.match(/^[A-Za-z_][A-Za-z0-9_]*/)) {
      if (stream.peek() === "=") {
        return "variableName";
      }
      stream.skipToEnd();
      return null;
    }
    if (stream.eat("=")) {
      return "operator";
    }
    if (stream.match(/^"[^"]*"/)) {
      return "string";
    }
    if (stream.match(/^'[^']*'/)) {
      return "string";
    }
    stream.next();
    return null;
  },
});

type VariableVersion = {
  id: number;
  value: string;
  created_at: string;
  user: { id: number; first_name: string; last_name: string } | null;
};

type Variable = {
  id: number;
  key: string;
  latest_version: VariableVersion | null;
  versions: VariableVersion[];
};

type EnvironmentType = {
  id: number;
  name: string;
  color: string;
  per_app_limit: number | null;
};

type Environment = {
  id: number;
  label: string;
  slug: string;
  color: AppColor | undefined;
  variables: Variable[];
  environment_type: EnvironmentType | null;
};

type AppData = {
  id: number;
  name: string;
  slug: string;
  environments: Environment[];
};

type ModalMode = "view" | "edit" | null;

export default function AppShow({
  app,
  setupTokens,
  canManage,
  canCreateVariable,
}: {
  app: AppData;
  setupTokens: Record<number, string>;
  canManage: boolean;
  canCreateVariable: boolean;
}) {
  const initialEnvId = (() => {
    if (typeof window !== "undefined") {
      const params = new URLSearchParams(window.location.search);
      const envParam = params.get("env");
      if (envParam && app.environments.some((e) => String(e.id) === envParam)) {
        return envParam;
      }
    }
    return String(app.environments[0]?.id || "");
  })();

  const [activeEnv, setActiveEnvState] = useState<string>(initialEnvId);
  const currentEnv = app.environments.find((e) => String(e.id) === activeEnv);

  const setActiveEnv = (envId: string) => {
    setActiveEnvState(envId);
    const url = new URL(window.location.href);
    url.searchParams.set("env", envId);
    window.history.replaceState({}, "", url.toString());
  };
  const [importOpened, { open: openImport, close: closeImport }] =
    useDisclosure(false);

  // Variable modal state
  const [selectedVariable, setSelectedVariable] = useState<Variable | null>(
    null,
  );
  const [modalMode, setModalMode] = useState<ModalMode>(null);
  const [editKey, setEditKey] = useState("");
  const [editValue, setEditValue] = useState("");

  // Rollback modal
  const [rollbackVariable, setRollbackVariable] = useState<Variable | null>(
    null,
  );

  // Delete confirmation
  const [deleteVariable, setDeleteVariable] = useState<Variable | null>(null);

  // Create form
  const [newKey, setNewKey] = useState("");
  const [newValue, setNewValue] = useState("");
  const [saving, setSaving] = useState(false);
  const [importContent, setImportContent] = useState("");
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [bulkOpened, { open: openBulk, close: closeBulk }] =
    useDisclosure(false);
  const [bulkContent, setBulkContent] = useState("");
  const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);

  useEffect(() => {
    setLayoutProps({
      breadcrumbs: [
        { title: "Apps", href: "/apps" },
        { title: app.name, href: `/apps/${app.id}` },
      ],
      headerAction: canManage ? (
        <Button
          component={Link}
          href={`/apps/${app.id}/edit`}
          leftSection={<FontAwesomeIcon icon={faPencil} />}
        >
          Manage App
        </Button>
      ) : undefined,
    });
  }, [app, canManage]);

  const currentSetupToken = currentEnv ? setupTokens[currentEnv.id] : null;
  const setupCommand =
    currentEnv && currentSetupToken
      ? `npx envault ${window.location.host} ${app.id} ${currentSetupToken}`
      : null;

  // Open view modal
  const openViewModal = (variable: Variable) => {
    setSelectedVariable(variable);
    setEditKey(variable.key);
    setEditValue(variable.latest_version?.value ?? "");
    setModalMode("view");
  };

  // Switch to edit mode
  const switchToEdit = () => {
    setModalMode("edit");
  };

  // Switch back to view mode
  const switchToView = () => {
    if (selectedVariable) {
      setEditKey(selectedVariable.key);
      setEditValue(selectedVariable.latest_version?.value ?? "");
    }
    setModalMode("view");
  };

  // Close variable modal
  const closeVariableModal = () => {
    setSelectedVariable(null);
    setModalMode(null);
  };

  const handleCreateVariable = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newKey.trim()) return;
    setSaving(true);
    router.post(
      `/apps/${app.id}/variables`,
      { key: newKey, value: newValue, environment_id: currentEnv?.id },
      {
        onSuccess: () => {
          setNewKey("");
          setNewValue("");
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleImport = () => {
    setSaving(true);
    router.post(
      `/apps/${app.id}/variables/import`,
      { env_content: importContent, environment_id: currentEnv?.id },
      {
        onSuccess: () => {
          closeImport();
          setImportContent("");
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleEditVariable = () => {
    if (!selectedVariable) return;
    setSaving(true);
    const data: Record<string, string> = {};
    if (editKey !== selectedVariable.key) data.key = editKey;
    if (editValue !== (selectedVariable.latest_version?.value ?? ""))
      data.value = editValue;

    router.patch(`/variables/${selectedVariable.id}`, data, {
      onSuccess: () => {
        closeVariableModal();
        setSaving(false);
      },
      onError: () => setSaving(false),
      preserveScroll: true,
    });
  };

  const handleDeleteVariable = () => {
    if (!deleteVariable) return;
    setSaving(true);
    router.delete(`/variables/${deleteVariable.id}`, {
      onSuccess: () => {
        setDeleteVariable(null);
        closeVariableModal();
        setSaving(false);
      },
      onError: () => setSaving(false),
      preserveScroll: true,
    });
  };

  const handleRollback = (versionId: number) => {
    if (!rollbackVariable) return;
    setSaving(true);
    router.post(
      `/variables/${rollbackVariable.id}/rollback`,
      { version_id: versionId },
      {
        onSuccess: () => {
          setRollbackVariable(null);
          closeVariableModal();
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const openBulkEdit = () => {
    const envVariables = currentEnv?.variables ?? [];
    const lines = envVariables.map(
      (v) => `${v.key}=${v.latest_version?.value ?? ""}`,
    );

    // Group by prefix (everything before the first underscore)
    let lastPrefix = "";
    const grouped: string[] = [];
    for (const line of lines) {
      const underscoreIndex = line.indexOf("_");
      const prefix =
        underscoreIndex > 0 ? line.substring(0, underscoreIndex) : line;
      if (lastPrefix && prefix !== lastPrefix) {
        grouped.push("");
      }
      lastPrefix = prefix;
      grouped.push(line);
    }

    setBulkContent(grouped.join("\n"));
    openBulk();
  };

  const isProductionEnv =
    currentEnv?.environment_type?.name?.toLowerCase() === "production";

  const handleBulkSaveAttempt = () => {
    if (isProductionEnv) {
      setBulkConfirmOpen(true);
      return;
    }
    executeBulkSave();
  };

  const executeBulkSave = () => {
    setSaving(true);
    setBulkConfirmOpen(false);
    router.post(
      `/apps/${app.id}/variables/import`,
      { env_content: bulkContent, environment_id: currentEnv?.id },
      {
        onSuccess: () => {
          closeBulk();
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (event) => {
      setImportContent(event.target?.result as string);
    };
    reader.readAsText(file);
    e.target.value = "";
  };

  return (
    <>
      <Head title={app.name} />

      {/* API path (app slug / environment slug) */}
      {(() => {
        const envSlug = (currentEnv?.slug ?? "").toLowerCase();
        const apiPath = envSlug ? `${app.slug}/${envSlug}` : app.slug;
        return (
          <Group gap="xs" mb="md">
            <Text size="sm" c="white">
              API path:
            </Text>
            <Code>{apiPath}</Code>
            <CopyButton value={apiPath}>
              {({ copied, copy }) => (
                <ActionIcon
                  variant="subtle"
                  color={copied ? "green" : "white"}
                  size="sm"
                  onClick={copy}
                  aria-label="Copy API path"
                >
                  <FontAwesomeIcon
                    icon={copied ? faCheck : faClipboard}
                    className="size-3"
                  />
                </ActionIcon>
              )}
            </CopyButton>
          </Group>
        );
      })()}

      {/* Set up this app */}
      {setupCommand && (
        <div className="mb-6 overflow-hidden rounded-md border border-border bg-background shadow">
          <div className="px-4 py-5 sm:px-6">
            <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
              Set up this app
            </h3>
          </div>
          <div className="flex items-stretch border-t border-gray-200 dark:border-gray-700">
            <div className="flex flex-1 items-center bg-gray-800 px-4 py-4 sm:px-6">
              <code className="font-mono text-sm text-gray-200">
                {setupCommand}
              </code>
            </div>
            <CopyButton value={setupCommand}>
              {({ copied, copy }) => (
                <button
                  onClick={copy}
                  className="flex items-center gap-2 border-l border-gray-700 bg-white px-6 py-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                  <FontAwesomeIcon
                    icon={copied ? faCheck : faClipboard}
                    className="size-4"
                  />
                  {copied ? "Copied" : "Copy"}
                </button>
              )}
            </CopyButton>
          </div>
        </div>
      )}

      {/* Environment Tabs & Variables */}
      <div className="mb-6 overflow-hidden rounded-md bg-background shadow">
        <div className="flex items-center justify-between px-4 py-5 sm:px-6">
          <div className="flex items-center gap-3">
            <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
              Variables
            </h3>
            {app.environments.length > 1 && (
              <Select
                data={app.environments.map((env) => ({
                  value: String(env.id),
                  label: env.label,
                }))}
                value={activeEnv}
                onChange={(v) => v && setActiveEnv(v)}
                size="xs"
                w={160}
                leftSection={
                  <span
                    className="size-2.5 rounded-full"
                    style={{
                      backgroundColor: `var(--mantine-color-${currentEnv?.color ?? "gray"}-5)`,
                    }}
                  />
                }
                styles={{
                  input: {
                    borderColor: `var(--mantine-color-${currentEnv?.color ?? "gray"}-5)`,
                    fontWeight: 600,
                  },
                }}
              />
            )}
            {app.environments.length === 1 && (
              <Badge
                size="sm"
                variant="light"
                color={currentEnv?.color ?? "gray"}
              >
                {currentEnv?.label}
              </Badge>
            )}
          </div>
          <Group gap="xs">
            <Button
              variant="subtle"
              size="xs"
              leftSection={<FontAwesomeIcon icon={faPencil} />}
              onClick={openBulkEdit}
            >
              Bulk Edit
            </Button>
            {(currentEnv?.variables.length ?? 0) > 0 && (
              <CopyButton
                value={(currentEnv?.variables ?? [])
                  .map((v) => `${v.key}=${v.latest_version?.value ?? ""}`)
                  .join("\n")}
              >
                {({ copied, copy }) => (
                  <Button
                    variant="subtle"
                    size="xs"
                    leftSection={
                      <FontAwesomeIcon icon={copied ? faCheck : faClipboard} />
                    }
                    onClick={copy}
                    color={copied ? "teal" : undefined}
                  >
                    {copied ? "Copied!" : "Copy .env"}
                  </Button>
                )}
              </CopyButton>
            )}
          </Group>
        </div>

        {(currentEnv?.variables.length ?? 0) > 0 ? (
          <ul>
            {(currentEnv?.variables ?? []).map((variable) => (
              <li
                key={variable.id}
                className="border-t border-gray-200 dark:border-gray-700"
              >
                <button
                  type="button"
                  onClick={() => openViewModal(variable)}
                  className="flex w-full items-center justify-between px-4 py-4 text-left transition-colors duration-150 hover:bg-gray-50 focus:bg-gray-100 focus:outline-none sm:px-6 dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                >
                  <div className="min-w-0 flex-1">
                    <Text size="sm" fw={500} className="text-primary!">
                      {variable.key}
                    </Text>
                    <Text size="xs" c="dimmed" mt={2}>
                      {variable.latest_version
                        ? `Updated ${new Date(variable.latest_version.created_at).toLocaleDateString()}`
                        : "No value set"}
                    </Text>
                  </div>
                  <FontAwesomeIcon
                    icon={faChevronRight}
                    className="size-4 text-gray-400"
                  />
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <div className="border-t border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
            <Text size="sm" c="dimmed">
              No variables here yet!
            </Text>
          </div>
        )}
      </div>

      {/* New variable (inline form) */}
      {canCreateVariable && (
        <div className="overflow-hidden rounded-md bg-background shadow">
          <div className="px-4 py-5 sm:px-6">
            <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
              New variable
            </h3>
          </div>
          <form onSubmit={handleCreateVariable}>
            <div className="border-t border-gray-200 px-4 py-5 sm:px-6 dark:border-gray-700">
              <div className="flex items-center gap-4 border-b border-gray-100 pb-5 dark:border-gray-800">
                <label className="w-16 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300">
                  Key
                </label>
                <TextInput
                  placeholder="MAIL_USERNAME"
                  value={newKey}
                  onChange={(e) =>
                    setNewKey(e.currentTarget.value.toUpperCase())
                  }
                  className="flex-1"
                />
              </div>
              <div className="flex items-center gap-4 pt-5">
                <label className="w-16 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300">
                  Value
                </label>
                <TextInput
                  placeholder="3c683983b21e1f"
                  value={newValue}
                  onChange={(e) => setNewValue(e.currentTarget.value)}
                  className="flex-1"
                />
              </div>
            </div>
            <div className="flex items-center justify-between border-t border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
              <Button variant="outline" onClick={openImport} type="button">
                Import
              </Button>
              <Button
                type="submit"
                loading={saving}
                disabled={!newKey.trim()}
                leftSection={<FontAwesomeIcon icon={faCheck} />}
              >
                Create
              </Button>
            </div>
          </form>
        </div>
      )}

      {/* Variable View/Edit Modal */}
      <Modal
        opened={!!selectedVariable && !!modalMode}
        onClose={closeVariableModal}
        withCloseButton={false}
        size="lg"
      >
        {selectedVariable && modalMode === "view" && (
          <>
            <Text size="xl" fw={700} mb="md">
              {selectedVariable.key}
            </Text>
            <div className="border-t border-gray-200 dark:border-gray-700">
              <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                <label className="w-16 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Key
                </label>
                <TextInput
                  value={selectedVariable.key}
                  readOnly
                  className="flex-1"
                  variant="filled"
                />
              </div>
              <div className="flex items-center gap-4 py-5">
                <label className="w-16 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Value
                </label>
                <TextInput
                  value={selectedVariable.latest_version?.value ?? ""}
                  readOnly
                  className="flex-1"
                  variant="filled"
                />
              </div>
            </div>
            <div className="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
              <Button variant="outline" onClick={closeVariableModal}>
                Close
              </Button>
              <Button
                leftSection={<FontAwesomeIcon icon={faPencil} />}
                onClick={switchToEdit}
              >
                Edit
              </Button>
            </div>
          </>
        )}

        {selectedVariable && modalMode === "edit" && (
          <>
            <Text size="xl" fw={700} mb="md">
              Edit {selectedVariable.key}
            </Text>
            <div className="border-t border-gray-200 dark:border-gray-700">
              <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                <label className="w-16 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Key
                </label>
                <TextInput
                  value={editKey}
                  onChange={(e) => setEditKey(e.currentTarget.value)}
                  className="flex-1"
                />
              </div>
              <div className="flex items-center gap-4 py-5">
                <label className="w-16 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Value
                </label>
                <div className="flex flex-1 items-center gap-2">
                  <TextInput
                    value={editValue}
                    onChange={(e) => setEditValue(e.currentTarget.value)}
                    className="flex-1"
                  />
                  {selectedVariable.versions.length > 1 && (
                    <ActionIcon
                      variant="default"
                      size="lg"
                      onClick={() => {
                        setRollbackVariable(selectedVariable);
                      }}
                      aria-label="Version history"
                    >
                      <FontAwesomeIcon icon={faRotateLeft} className="size-4" />
                    </ActionIcon>
                  )}
                </div>
              </div>
            </div>
            <div className="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
              <Group gap="xs">
                <Button
                  variant="outline"
                  leftSection={<FontAwesomeIcon icon={faChevronLeft} />}
                  onClick={switchToView}
                >
                  Back
                </Button>
                <ActionIcon
                  variant="filled"
                  color="red"
                  size="lg"
                  onClick={() => setDeleteVariable(selectedVariable)}
                  aria-label="Delete variable"
                >
                  <FontAwesomeIcon icon={faTrash} />
                </ActionIcon>
              </Group>
              <Button
                onClick={handleEditVariable}
                loading={saving}
                leftSection={<FontAwesomeIcon icon={faCheck} />}
              >
                Save
              </Button>
            </div>
          </>
        )}
      </Modal>

      {/* Bulk Edit Modal */}
      <Modal
        opened={bulkOpened}
        onClose={closeBulk}
        title="Bulk Edit Variables"
        size="lg"
      >
        <Stack>
          <Text size="sm" c="dimmed">
            Edit all variables as a .env file. Changes will be saved when you
            click Save.
          </Text>
          <div className="overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
            <CodeMirror
              value={bulkContent}
              onChange={setBulkContent}
              extensions={[envLanguage]}
              theme={oneDark}
              minHeight="300px"
              maxHeight="500px"
              basicSetup={{
                lineNumbers: true,
                foldGutter: false,
                highlightActiveLine: true,
                bracketMatching: false,
              }}
            />
          </div>
          <Group justify="flex-end">
            <Button variant="outline" onClick={closeBulk}>
              Cancel
            </Button>
            <Button
              onClick={handleBulkSaveAttempt}
              loading={saving}
              disabled={!bulkContent.trim()}
              leftSection={<FontAwesomeIcon icon={faCheck} />}
            >
              Save
            </Button>
          </Group>
        </Stack>
      </Modal>

      {/* Production Bulk Edit Confirmation */}
      <Modal
        opened={bulkConfirmOpen}
        onClose={() => setBulkConfirmOpen(false)}
        withCloseButton={false}
        centered
      >
        <div className="flex gap-4 p-2">
          <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-yellow-100">
            <svg
              className="size-6 text-yellow-600"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={1.5}
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
              />
            </svg>
          </div>
          <div>
            <Text size="lg" fw={700}>
              You're editing Production
            </Text>
            <Text size="sm" c="dimmed" mt={4}>
              You are about to bulk edit variables in the{" "}
              <strong>{currentEnv?.label}</strong> environment for{" "}
              <strong>{app.name}</strong>. Are you sure you want to continue?
            </Text>
          </div>
        </div>
        <Group
          justify="flex-end"
          mt="lg"
          className="border-t border-gray-200 pt-4 dark:border-gray-700"
        >
          <Button variant="outline" onClick={() => setBulkConfirmOpen(false)}>
            Cancel
          </Button>
          <Button color="red" onClick={executeBulkSave} loading={saving}>
            Yes, save changes
          </Button>
        </Group>
      </Modal>

      {/* Import Modal */}
      <Modal
        opened={importOpened}
        onClose={closeImport}
        title="Import Variables"
        size="lg"
      >
        <Stack>
          <Text size="sm" c="dimmed">
            Paste the contents of a .env file below. Existing variables will be
            updated with new values.
          </Text>
          <Textarea
            label="Environment Variables"
            placeholder={"DB_HOST=localhost\nDB_PORT=3306\nDB_NAME=myapp"}
            value={importContent}
            onChange={(e) => setImportContent(e.currentTarget.value)}
            autosize
            minRows={8}
          />
          <input
            type="file"
            ref={fileInputRef}
            accept=".env,.txt"
            onChange={handleFileUpload}
            className="hidden"
          />
          <Button
            variant="outline"
            onClick={() => fileInputRef.current?.click()}
            leftSection={<FontAwesomeIcon icon={faUpload} />}
          >
            Upload .env file
          </Button>
          <Group justify="flex-end">
            <Button variant="outline" onClick={closeImport}>
              Cancel
            </Button>
            <Button
              onClick={handleImport}
              loading={saving}
              disabled={!importContent.trim()}
            >
              Import
            </Button>
          </Group>
        </Stack>
      </Modal>

      {/* Rollback Modal */}
      <Modal
        opened={!!rollbackVariable}
        onClose={() => setRollbackVariable(null)}
        title={`Rollback ${rollbackVariable?.key ?? "Variable"}`}
        size="lg"
      >
        <Stack>
          <Text size="sm" c="dimmed">
            Select a previous version to restore:
          </Text>
          {rollbackVariable?.versions.map((version, index) => (
            <div
              key={version.id}
              className="rounded border border-gray-200 p-3 dark:border-gray-700"
            >
              <Group justify="space-between" align="flex-start">
                <div className="min-w-0 flex-1">
                  <Group gap="xs" align="center">
                    <Text size="xs" c="dimmed">
                      {new Date(version.created_at).toLocaleString()}
                    </Text>
                    {version.user && (
                      <Text size="xs" c="dimmed">
                        by{" "}
                        <Text span fw={500} size="xs">
                          {version.user.first_name} {version.user.last_name}
                        </Text>
                      </Text>
                    )}
                    {index === 0 && (
                      <Badge size="xs" color="blue">
                        Current
                      </Badge>
                    )}
                  </Group>
                  <Code block mt={4} className="text-xs">
                    {version.value}
                  </Code>
                </div>
                {index > 0 && (
                  <Button
                    size="xs"
                    variant="subtle"
                    onClick={() => handleRollback(version.id)}
                    loading={saving}
                  >
                    Restore
                  </Button>
                )}
              </Group>
            </div>
          ))}
        </Stack>
      </Modal>

      {/* Delete Variable Confirmation */}
      <Modal
        opened={!!deleteVariable}
        onClose={() => setDeleteVariable(null)}
        withCloseButton={false}
        centered
      >
        <div className="flex gap-4 p-2">
          <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100">
            <svg
              className="size-6 text-red-600"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={1.5}
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
              />
            </svg>
          </div>
          <div>
            <Text size="lg" fw={700}>
              Delete this variable
            </Text>
            <Text size="sm" c="dimmed" mt={4}>
              Are you sure you want to delete {deleteVariable?.key}? This action
              is permanent.
            </Text>
          </div>
        </div>
        <Group
          justify="flex-end"
          mt="lg"
          className="border-t border-gray-200 pt-4 dark:border-gray-700"
        >
          <Button variant="outline" onClick={() => setDeleteVariable(null)}>
            Cancel
          </Button>
          <Button color="red" onClick={handleDeleteVariable} loading={saving}>
            Confirm
          </Button>
        </Group>
      </Modal>
    </>
  );
}

AppShow.layout = {
  breadcrumbs: [
    { title: "Apps", href: "/apps" },
    { title: "App Details", href: "#" },
  ],
};
