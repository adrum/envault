import { AppColor } from "@/colors";
import {
  faCheck,
  faChevronLeft,
  faInfoCircle,
  faPencil,
  faPlus,
  faTrash,
  faUserPlus,
  faUsers,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Head, Link, router, setLayoutProps, usePage } from "@inertiajs/react";
import {
  ActionIcon,
  Badge,
  Button,
  Group,
  Modal,
  Select,
  Text,
  TextInput,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { useEffect, useState } from "react";

type Collaborator = {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  pivot: { role: string };
};

type AvailableUser = {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
};

type EnvironmentTypeData = {
  id: number;
  name: string;
  color: string;
  per_app_limit: number | null;
};

type EnvironmentData = {
  id: number;
  label: string;
  slug: string;
  color: string;
  sort_order: number;
  environment_type_id: number | null;
  custom_label: string | null;
  environment_type: EnvironmentTypeData | null;
};

type AppData = {
  id: number;
  name: string;
  slug: string;
  slack_notification_channel: string | null;
  slack_notification_webhook_url: string | null;
  collaborators: Collaborator[];
  environments: EnvironmentData[];
};

type AdminUser = {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  role: string;
};

export default function AppEdit({
  app,
  adminUsers,
  availableUsers,
  environmentTypes = [],
}: {
  app: AppData;
  adminUsers: AdminUser[];
  availableUsers: AvailableUser[];
  environmentTypes: EnvironmentTypeData[];
}) {
  const { errors: pageErrors } = usePage().props;
  const [name, setName] = useState(app.name);
  const [slug, setSlug] = useState(app.slug);
  const [removeCollab, setRemoveCollab] = useState<Collaborator | null>(null);
  const [slackChannel, setSlackChannel] = useState(
    app.slack_notification_channel || "",
  );
  const [slackWebhook, setSlackWebhook] = useState(
    app.slack_notification_webhook_url || "",
  );
  const [deleteOpened, { open: openDelete, close: closeDelete }] =
    useDisclosure(false);
  const [deleteConfirmName, setDeleteConfirmName] = useState("");
  const [saving, setSaving] = useState(false);
  const [selectedUser, setSelectedUser] = useState<string | null>(null);
  const [selectedRole, setSelectedRole] = useState<string>("collaborator");

  // Environment state
  const [newEnvTypeId, setNewEnvTypeId] = useState<string | null>(null);
  const [newEnvCustomLabel, setNewEnvCustomLabel] = useState("");
  const [editEnv, setEditEnv] = useState<EnvironmentData | null>(null);
  const [editEnvTypeId, setEditEnvTypeId] = useState<string>("");
  const [editEnvCustomLabel, setEditEnvCustomLabel] = useState("");
  const [editEnvSlug, setEditEnvSlug] = useState("");
  const [deleteEnv, setDeleteEnv] = useState<EnvironmentData | null>(null);
  const [deleteConfirmLabel, setDeleteConfirmLabel] = useState("");

  const selectedNewType = environmentTypes.find(
    (t) => String(t.id) === newEnvTypeId,
  );
  const editEnvType = environmentTypes.find(
    (t) => String(t.id) === editEnvTypeId,
  );

  const handleUpdateDetails = (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    router.patch(
      `/apps/${app.id}`,
      { name, slug },
      {
        onFinish: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleDelete = () => {
    router.delete(`/apps/${app.id}`, {
      data: { confirm_name: deleteConfirmName },
    });
  };

  const handleUpdateNotifications = (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    router.patch(
      `/apps/${app.id}/notifications`,
      {
        slack_notification_channel: slackChannel || null,
        slack_notification_webhook_url: slackWebhook || null,
      },
      {
        onFinish: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleAddCollaborator = () => {
    if (!selectedUser) return;
    setSaving(true);
    router.post(
      `/apps/${app.id}/collaborators`,
      {
        user_id: selectedUser,
        role: selectedRole,
      },
      {
        onSuccess: () => {
          setSelectedUser(null);
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleRemoveCollaborator = () => {
    if (!removeCollab) return;
    router.delete(`/apps/${app.id}/collaborators`, {
      data: { user_id: removeCollab.id },
      preserveScroll: true,
      onSuccess: () => setRemoveCollab(null),
    });
  };

  const handleAddEnvironment = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newEnvTypeId) return;
    setSaving(true);
    router.post(
      `/apps/${app.id}/environments`,
      {
        environment_type_id: newEnvTypeId,
        custom_label: newEnvCustomLabel || null,
      },
      {
        onSuccess: () => {
          setNewEnvTypeId(null);
          setNewEnvCustomLabel("");
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleUpdateEnvironment = () => {
    if (!editEnv) return;
    setSaving(true);
    router.patch(
      `/environments/${editEnv.id}`,
      {
        environment_type_id: editEnvTypeId,
        custom_label: editEnvCustomLabel || null,
        slug: editEnvSlug,
      },
      {
        onSuccess: () => {
          setEditEnv(null);
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleDeleteEnvironment = () => {
    if (!deleteEnv) return;
    setSaving(true);
    router.delete(`/environments/${deleteEnv.id}`, {
      data: { confirm_label: deleteConfirmLabel },
      onSuccess: () => {
        setDeleteEnv(null);
        setDeleteConfirmLabel("");
        setSaving(false);
      },
      onError: () => setSaving(false),
      preserveScroll: true,
    });
  };

  const openEditEnv = (env: EnvironmentData) => {
    setEditEnvTypeId(String(env.environment_type_id ?? ""));
    setEditEnvCustomLabel(env.custom_label || "");
    setEditEnvSlug(env.slug || "");
    setEditEnv(env);
  };

  useEffect(() => {
    setLayoutProps({
      breadcrumbs: [
        { title: "Apps", href: "/apps" },
        { title: `Edit ${app.name}`, href: `/apps/${app.id}/edit` },
      ],
      headerAction: (
        <Button
          component={Link}
          href={`/apps/${app.id}`}
          variant="outline"
          className="border-gray-500! text-white! hover:bg-gray-700!"
          leftSection={<FontAwesomeIcon icon={faChevronLeft} />}
        >
          Back
        </Button>
      ),
    });
  }, [app]);

  return (
    <>
      <Head title={`Edit ${app.name}`} />

      {/* Details card */}
      <div className="mb-6 overflow-hidden rounded-md bg-background shadow">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
            Details
          </h3>
        </div>
        <form onSubmit={handleUpdateDetails}>
          <div className="border-t border-gray-200 px-4 py-5 sm:px-6 dark:border-gray-700">
            <div className="flex items-center gap-4">
              <label className="w-20 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300">
                Name
              </label>
              <TextInput
                value={name}
                onChange={(e) => setName(e.currentTarget.value)}
                className="flex-1"
                required
              />
            </div>
          </div>
          <div className="border-t border-gray-200 px-4 py-5 sm:px-6 dark:border-gray-700">
            <div className="flex items-center gap-4">
              <label className="w-20 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300">
                Slug
              </label>
              <TextInput
                value={slug}
                onChange={(e) => setSlug(e.currentTarget.value)}
                className="flex-1"
                description="Used in the Vault path (e.g. /v1/secret/data/<slug>/<env>)."
                error={pageErrors?.slug}
                required
              />
            </div>
          </div>
          <div className="flex items-center justify-between border-t border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
            <ActionIcon
              variant="filled"
              color="red"
              size="lg"
              onClick={openDelete}
              aria-label="Delete app"
            >
              <FontAwesomeIcon icon={faTrash} />
            </ActionIcon>
            <Button
              type="submit"
              loading={saving}
              leftSection={<FontAwesomeIcon icon={faCheck} />}
            >
              Save
            </Button>
          </div>
        </form>
      </div>

      {/* Environments card */}
      <div className="mb-6 overflow-hidden rounded-md bg-background shadow">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
            Environments
          </h3>
        </div>

        <ul>
          {(app.environments || []).map((env) => (
            <li
              key={env.id}
              className="border-t border-gray-200 dark:border-gray-700"
            >
              <div className="flex items-center justify-between px-4 py-4 sm:px-6">
                <div className="flex items-center gap-3">
                  <Badge
                    size="sm"
                    variant="light"
                    color={env.color as AppColor}
                  >
                    {env.label}
                  </Badge>
                  {env.custom_label && env.environment_type && (
                    <Text size="xs" c="dimmed">
                      {env.environment_type.name}
                    </Text>
                  )}
                </div>
                <Group gap="xs">
                  <ActionIcon
                    variant="subtle"
                    size="sm"
                    onClick={() => openEditEnv(env)}
                    aria-label="Edit environment"
                  >
                    <FontAwesomeIcon icon={faPencil} className="size-3" />
                  </ActionIcon>
                  <ActionIcon
                    variant="subtle"
                    color="red"
                    size="sm"
                    onClick={() => {
                      setDeleteEnv(env);
                      setDeleteConfirmLabel("");
                    }}
                    aria-label="Delete environment"
                  >
                    <FontAwesomeIcon icon={faTrash} className="size-3" />
                  </ActionIcon>
                </Group>
              </div>
            </li>
          ))}
        </ul>

        {/* Add environment */}
        {environmentTypes.length > 0 && (
          <div className="border-t border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
            <form
              onSubmit={handleAddEnvironment}
              className="flex items-center gap-3"
            >
              <Select
                placeholder="Select type..."
                data={environmentTypes.map((t) => ({
                  value: String(t.id),
                  label: t.name,
                }))}
                value={newEnvTypeId}
                onChange={setNewEnvTypeId}
                className="flex-1"
                leftSection={
                  selectedNewType && (
                    <span
                      className="size-3 rounded-full"
                      style={{
                        backgroundColor: `var(--mantine-color-${selectedNewType.color}-5)`,
                      }}
                    />
                  )
                }
                renderOption={({ option }) => {
                  const t = environmentTypes.find(
                    (et) => String(et.id) === option.value,
                  );
                  return (
                    <div className="flex items-center gap-2">
                      <span
                        className="size-3 shrink-0 rounded-full"
                        style={{
                          backgroundColor: `var(--mantine-color-${t?.color ?? "gray"}-5)`,
                        }}
                      />
                      <span>{option.label}</span>
                    </div>
                  );
                }}
              />
              {selectedNewType && selectedNewType.per_app_limit !== 1 && (
                <TextInput
                  placeholder="Custom label (optional)"
                  value={newEnvCustomLabel}
                  onChange={(e) => setNewEnvCustomLabel(e.currentTarget.value)}
                  className="flex-1"
                />
              )}
              <Button
                type="submit"
                disabled={!newEnvTypeId}
                loading={saving}
                leftSection={<FontAwesomeIcon icon={faPlus} />}
                size="sm"
              >
                Add
              </Button>
            </form>
          </div>
        )}
      </div>

      {/* Edit Environment Modal */}
      <Modal
        opened={!!editEnv}
        onClose={() => setEditEnv(null)}
        title={`Edit ${editEnv?.label}`}
      >
        <div className="space-y-4">
          <Select
            label="Environment Type"
            data={environmentTypes.map((t) => ({
              value: String(t.id),
              label: t.name,
            }))}
            value={editEnvTypeId}
            onChange={(v) => v && setEditEnvTypeId(v)}
            error={(pageErrors as any).environment_type_id}
            leftSection={
              editEnvType && (
                <span
                  className="size-3 rounded-full"
                  style={{
                    backgroundColor: `var(--mantine-color-${editEnvType.color}-5)`,
                  }}
                />
              )
            }
            renderOption={({ option }) => {
              const t = environmentTypes.find(
                (et) => String(et.id) === option.value,
              );
              return (
                <div className="flex items-center gap-2">
                  <span
                    className="size-3 shrink-0 rounded-full"
                    style={{
                      backgroundColor: `var(--mantine-color-${t?.color ?? "gray"}-5)`,
                    }}
                  />
                  <span>{option.label}</span>
                </div>
              );
            }}
          />
          {editEnvType && editEnvType.per_app_limit !== 1 && (
            <TextInput
              label="Custom Label"
              placeholder={editEnvType?.name || ""}
              value={editEnvCustomLabel}
              onChange={(e) => setEditEnvCustomLabel(e.currentTarget.value)}
              error={(pageErrors as any).custom_label}
            />
          )}
          <TextInput
            label="Slug"
            value={editEnvSlug}
            onChange={(e) => setEditEnvSlug(e.currentTarget.value)}
            error={(pageErrors as any).slug}
          />
          <Group justify="flex-end">
            <Button variant="outline" onClick={() => setEditEnv(null)}>
              Cancel
            </Button>
            <Button
              onClick={handleUpdateEnvironment}
              loading={saving}
              leftSection={<FontAwesomeIcon icon={faCheck} />}
            >
              Save
            </Button>
          </Group>
        </div>
      </Modal>

      {/* Delete Environment Modal */}
      <Modal
        opened={!!deleteEnv}
        onClose={() => setDeleteEnv(null)}
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
              Delete this environment
            </Text>
            <Text size="sm" c="dimmed" mt={4}>
              This will permanently delete all variables in the "
              {deleteEnv?.label}" environment. Type{" "}
              <strong>{deleteEnv?.label}</strong> to confirm.
            </Text>
            <TextInput
              mt="sm"
              placeholder={deleteEnv?.label}
              value={deleteConfirmLabel}
              onChange={(e) => setDeleteConfirmLabel(e.currentTarget.value)}
              error={(pageErrors as any).confirm_label}
            />
            {(pageErrors as any).environment && (
              <Text size="sm" c="red" mt={8}>
                {(pageErrors as any).environment}
              </Text>
            )}
          </div>
        </div>
        <Group
          justify="flex-end"
          mt="lg"
          className="border-t border-gray-200 pt-4 dark:border-gray-700"
        >
          <Button variant="outline" onClick={() => setDeleteEnv(null)}>
            Cancel
          </Button>
          <Button
            color="red"
            onClick={handleDeleteEnvironment}
            loading={saving}
            disabled={deleteConfirmLabel !== deleteEnv?.label}
          >
            Confirm
          </Button>
        </Group>
      </Modal>

      {/* Collaborators card */}
      <div className="mb-6 overflow-hidden rounded-md bg-background shadow">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
            Collaborators
          </h3>
        </div>

        <ul>
          {/* Admin/owner users always shown */}
          {(adminUsers || []).map((user) => (
            <li
              key={`admin-${user.id}`}
              className="border-t border-gray-200 dark:border-gray-700"
            >
              <div className="px-4 py-4 sm:px-6">
                <Text size="sm" fw={600}>
                  {user.first_name} {user.last_name}
                </Text>
                <div className="mt-1 flex items-center gap-1.5">
                  <FontAwesomeIcon
                    icon={faUsers}
                    className="size-4 text-gray-400"
                  />
                  <Text size="sm" c="dimmed" tt="capitalize">
                    {user.role}
                  </Text>
                </div>
              </div>
            </li>
          ))}
          {/* Manually added collaborators with delete */}
          {(app.collaborators || []).map((collab) => (
            <li
              key={`collab-${collab.id}`}
              className="border-t border-gray-200 dark:border-gray-700"
            >
              <div className="flex items-center justify-between px-4 py-4 sm:px-6">
                <div>
                  <Text size="sm" fw={600}>
                    {collab.first_name} {collab.last_name}
                  </Text>
                  <div className="mt-1 flex items-center gap-1.5">
                    <FontAwesomeIcon
                      icon={faUsers}
                      className="size-4 text-gray-400"
                    />
                    <Text size="sm" c="dimmed" tt="capitalize">
                      {collab.pivot.role}
                    </Text>
                  </div>
                </div>
                <ActionIcon
                  variant="filled"
                  color="red"
                  size="lg"
                  onClick={() => setRemoveCollab(collab)}
                  aria-label={`Remove ${collab.first_name}`}
                >
                  <FontAwesomeIcon icon={faTrash} />
                </ActionIcon>
              </div>
            </li>
          ))}
          {(adminUsers || []).length === 0 &&
            (app.collaborators || []).length === 0 && (
              <li className="border-t border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
                <Text size="sm" c="dimmed">
                  No collaborators yet.
                </Text>
              </li>
            )}
        </ul>

        {/* Add collaborator */}
        {(availableUsers || []).length > 0 && (
          <div className="border-t border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
            <div className="flex items-center gap-3">
              <Select
                placeholder="Select a user..."
                data={availableUsers.map((u) => ({
                  value: String(u.id),
                  label: `${u.first_name} ${u.last_name}`,
                }))}
                value={selectedUser}
                onChange={setSelectedUser}
                className="flex-1"
                searchable
              />
              <Button
                onClick={handleAddCollaborator}
                disabled={!selectedUser}
                loading={saving}
                leftSection={<FontAwesomeIcon icon={faUserPlus} />}
              >
                Add
              </Button>
            </div>
          </div>
        )}
      </div>

      {/* Slack Notifications card */}
      <div className="overflow-hidden rounded-md bg-background shadow">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
            Slack Notifications
          </h3>
        </div>
        <form onSubmit={handleUpdateNotifications}>
          <div className="border-t border-gray-200 dark:border-gray-700">
            <div className="flex items-center gap-4 border-b border-gray-100 px-4 py-5 sm:px-6 dark:border-gray-800">
              <label className="w-32 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                Webhook URL
              </label>
              <TextInput
                placeholder="https://hooks.slack.com/services/..."
                value={slackWebhook}
                onChange={(e) => setSlackWebhook(e.currentTarget.value)}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-4 px-4 py-5 sm:px-6">
              <label className="w-32 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                Channel
              </label>
              <TextInput
                placeholder="# general"
                value={slackChannel}
                onChange={(e) => setSlackChannel(e.currentTarget.value)}
                className="flex-1"
              />
            </div>
          </div>
          <div className="flex items-center justify-between border-t border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
            <div className="flex items-center gap-2 text-sm text-gray-500">
              <FontAwesomeIcon icon={faInfoCircle} className="size-4" />
              <span>
                {slackWebhook && slackChannel
                  ? "Slack notifications enabled."
                  : "Slack notifications disabled."}
              </span>
            </div>
            <Button
              type="submit"
              loading={saving}
              leftSection={<FontAwesomeIcon icon={faCheck} />}
            >
              Save
            </Button>
          </div>
        </form>
      </div>

      {/* Remove Collaborator Confirmation */}
      <Modal
        opened={!!removeCollab}
        onClose={() => setRemoveCollab(null)}
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
              Remove this collaborator
            </Text>
            <Text size="sm" c="dimmed" mt={4}>
              Are you sure you want to remove {removeCollab?.first_name}{" "}
              {removeCollab?.last_name} from project collaborator? They may no
              longer be able to view this project.
            </Text>
          </div>
        </div>
        <Group
          justify="flex-end"
          mt="lg"
          className="border-t border-gray-200 pt-4 dark:border-gray-700"
        >
          <Button variant="outline" onClick={() => setRemoveCollab(null)}>
            Cancel
          </Button>
          <Button color="red" onClick={handleRemoveCollaborator}>
            Confirm
          </Button>
        </Group>
      </Modal>

      {/* Delete Confirmation */}
      <Modal
        opened={deleteOpened}
        onClose={closeDelete}
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
              Delete this app
            </Text>
            <Text size="sm" c="dimmed" mt={4}>
              This will permanently delete all environments and variables. Type{" "}
              <strong>{app.name}</strong> to confirm.
            </Text>
            <TextInput
              mt="sm"
              placeholder={app.name}
              value={deleteConfirmName}
              onChange={(e) => setDeleteConfirmName(e.currentTarget.value)}
              error={(pageErrors as any).confirm_name}
            />
          </div>
        </div>
        <Group
          justify="flex-end"
          mt="lg"
          className="border-t border-gray-200 pt-4 dark:border-gray-700"
        >
          <Button variant="outline" onClick={closeDelete}>
            Cancel
          </Button>
          <Button
            color="red"
            onClick={handleDelete}
            disabled={deleteConfirmName !== app.name}
          >
            Confirm
          </Button>
        </Group>
      </Modal>
    </>
  );
}

AppEdit.layout = {
  breadcrumbs: [
    { title: "Apps", href: "/apps" },
    { title: "Edit App", href: "#" },
  ],
};
