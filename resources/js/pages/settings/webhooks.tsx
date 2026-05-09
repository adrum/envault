import Heading from "@/components/heading";
import {
  faPaperPlane,
  faPencil,
  faPlus,
  faTrash,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Head, router } from "@inertiajs/react";
import {
  ActionIcon,
  Badge,
  Button,
  Checkbox,
  Code,
  Group,
  Modal,
  ScrollArea,
  Stack,
  Switch,
  Table,
  Tabs,
  Text,
  TextInput,
  Title,
  Tooltip,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import { useMemo, useState } from "react";

type ScopeType = "app" | "environment" | "environment_type";

type Subscription = { type: ScopeType; id: number; label?: string };

type Webhook = {
  id: number;
  name: string;
  url: string;
  events: string[];
  active: boolean;
  all_apps: boolean;
  created_at: string;
  subscriptions: Subscription[];
};

type Delivery = {
  id: number;
  webhook_id: number | null;
  webhook_name: string | null;
  event: string;
  url: string;
  response_status: number | null;
  error: string | null;
  attempt: number;
  delivered_at: string | null;
  created_at: string;
  success: boolean;
};

type ScopeOptions = {
  apps: {
    id: number;
    name: string;
    environments: { id: number; label: string }[];
  }[];
  environment_types: { id: number; name: string; color: string }[];
};

type Props = {
  webhooks: Webhook[];
  deliveries: Delivery[];
  available_events: string[];
  scope_options: ScopeOptions;
};

const subKey = (s: Subscription) => `${s.type}:${s.id}`;

export default function WebhooksPage({
  webhooks,
  deliveries,
  available_events,
  scope_options,
}: Props) {
  const [opened, { open, close }] = useDisclosure(false);
  const [editing, setEditing] = useState<Webhook | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Webhook | null>(null);

  const openCreate = () => {
    setEditing(null);
    open();
  };

  const openEdit = (w: Webhook) => {
    setEditing(w);
    open();
  };

  const handleTest = (w: Webhook) => {
    router.post(
      `/settings/webhooks/${w.id}/test`,
      {},
      { preserveScroll: true },
    );
  };

  const handleDelete = () => {
    if (!deleteTarget) return;
    router.delete(`/settings/webhooks/${deleteTarget.id}`, {
      onSuccess: () => setDeleteTarget(null),
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title="Webhooks" />
      <Heading
        title="Webhooks"
        description="Send HTTP callbacks when events happen across apps and environments"
      />

      <Tabs defaultValue="webhooks" mt="md">
        <Tabs.List>
          <Tabs.Tab value="webhooks">Webhooks ({webhooks.length})</Tabs.Tab>
          <Tabs.Tab value="logs">Logs ({deliveries.length})</Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="webhooks" pt="md">
          <Group justify="flex-end" mb="sm">
            <Button
              leftSection={<FontAwesomeIcon icon={faPlus} />}
              onClick={openCreate}
            >
              New webhook
            </Button>
          </Group>

          {webhooks.length === 0 ? (
            <Text c="dimmed" size="sm">
              No webhooks yet.
            </Text>
          ) : (
            <Stack gap="sm">
              {webhooks.map((w) => (
                <div
                  key={w.id}
                  className="rounded-md border border-border bg-background p-4"
                >
                  <Group justify="space-between" align="flex-start">
                    <Stack gap={4}>
                      <Group gap="xs">
                        <Title order={5}>{w.name}</Title>
                        {!w.active && <Badge color="gray">Disabled</Badge>}
                      </Group>
                      <Code>{w.url}</Code>
                      <Group gap={4}>
                        {w.events.map((e) => (
                          <Badge key={e} variant="light" size="sm">
                            {e}
                          </Badge>
                        ))}
                      </Group>
                      <Group gap={4}>
                        {w.all_apps ? (
                          <Badge color="blue" size="sm">
                            All apps (current & future)
                          </Badge>
                        ) : (
                          w.subscriptions.map((s) => (
                            <Badge
                              key={subKey(s)}
                              color={
                                s.type === "app"
                                  ? "blue"
                                  : s.type === "environment"
                                    ? "violet"
                                    : "teal"
                              }
                              size="sm"
                            >
                              {s.label}
                            </Badge>
                          ))
                        )}
                      </Group>
                    </Stack>
                    <Group gap="xs">
                      <Tooltip label="Send test event">
                        <ActionIcon
                          variant="default"
                          onClick={() => handleTest(w)}
                        >
                          <FontAwesomeIcon icon={faPaperPlane} />
                        </ActionIcon>
                      </Tooltip>
                      <Tooltip label="Edit">
                        <ActionIcon
                          variant="default"
                          onClick={() => openEdit(w)}
                        >
                          <FontAwesomeIcon icon={faPencil} />
                        </ActionIcon>
                      </Tooltip>
                      <Tooltip label="Delete">
                        <ActionIcon
                          color="red"
                          variant="light"
                          onClick={() => setDeleteTarget(w)}
                        >
                          <FontAwesomeIcon icon={faTrash} />
                        </ActionIcon>
                      </Tooltip>
                    </Group>
                  </Group>
                </div>
              ))}
            </Stack>
          )}
        </Tabs.Panel>

        <Tabs.Panel value="logs" pt="md">
          {deliveries.length === 0 ? (
            <Text c="dimmed" size="sm">
              No deliveries yet.
            </Text>
          ) : (
            <ScrollArea>
              <Table striped withTableBorder>
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th>When</Table.Th>
                    <Table.Th>Webhook</Table.Th>
                    <Table.Th>Event</Table.Th>
                    <Table.Th>Status</Table.Th>
                    <Table.Th>Attempt</Table.Th>
                    <Table.Th>Detail</Table.Th>
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {deliveries.map((d) => (
                    <Table.Tr key={d.id}>
                      <Table.Td>
                        <Text size="xs" c="dimmed">
                          {new Date(d.created_at).toLocaleString()}
                        </Text>
                      </Table.Td>
                      <Table.Td>{d.webhook_name ?? "(deleted)"}</Table.Td>
                      <Table.Td>
                        <Code>{d.event}</Code>
                      </Table.Td>
                      <Table.Td>
                        {d.error ? (
                          <Badge color="red">error</Badge>
                        ) : d.success ? (
                          <Badge color="green">{d.response_status}</Badge>
                        ) : (
                          <Badge color="orange">
                            {d.response_status ?? "—"}
                          </Badge>
                        )}
                      </Table.Td>
                      <Table.Td>{d.attempt}</Table.Td>
                      <Table.Td>
                        <Text size="xs" lineClamp={2}>
                          {d.error ?? d.url}
                        </Text>
                      </Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            </ScrollArea>
          )}
        </Tabs.Panel>
      </Tabs>

      <WebhookFormModal
        opened={opened}
        onClose={close}
        webhook={editing}
        availableEvents={available_events}
        scopeOptions={scope_options}
      />

      <Modal
        opened={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        title="Delete webhook"
      >
        <Stack>
          <Text size="sm">
            Delete webhook <strong>{deleteTarget?.name}</strong>? This cannot be
            undone.
          </Text>
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setDeleteTarget(null)}>
              Cancel
            </Button>
            <Button color="red" onClick={handleDelete}>
              Delete
            </Button>
          </Group>
        </Stack>
      </Modal>
    </>
  );
}

type FormProps = {
  opened: boolean;
  onClose: () => void;
  webhook: Webhook | null;
  availableEvents: string[];
  scopeOptions: ScopeOptions;
};

function WebhookFormModal({
  opened,
  onClose,
  webhook,
  availableEvents,
  scopeOptions,
}: FormProps) {
  const [name, setName] = useState("");
  const [url, setUrl] = useState("");
  const [active, setActive] = useState(true);
  const [allApps, setAllApps] = useState(false);
  const [events, setEvents] = useState<string[]>([]);
  const [scope, setScope] = useState<Subscription[]>([]);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  useMemo(() => {
    if (opened) {
      setName(webhook?.name ?? "");
      setUrl(webhook?.url ?? "");
      setActive(webhook?.active ?? true);
      setAllApps(webhook?.all_apps ?? false);
      setEvents(webhook?.events ?? []);
      setScope(
        webhook?.subscriptions.map((s) => ({ type: s.type, id: s.id })) ?? [],
      );
      setErrors({});
    }
  }, [opened, webhook]);

  const toggleEvent = (e: string) => {
    setEvents((prev) =>
      prev.includes(e) ? prev.filter((x) => x !== e) : [...prev, e],
    );
  };

  const isSelected = (type: ScopeType, id: number) =>
    scope.some((s) => s.type === type && s.id === id);

  const toggleScope = (type: ScopeType, id: number) => {
    setScope((prev) =>
      isSelected(type, id)
        ? prev.filter((s) => !(s.type === type && s.id === id))
        : [...prev, { type, id }],
    );
  };

  const submit = () => {
    setSaving(true);
    setErrors({});
    const payload = {
      name,
      url,
      active,
      all_apps: allApps,
      events,
      subscriptions: allApps
        ? []
        : scope.map((s) => ({ type: s.type, id: s.id })),
    };
    const onError = (errs: Record<string, string>) => {
      setErrors(errs);
      setSaving(false);
    };
    const onSuccess = () => {
      setSaving(false);
      onClose();
    };
    if (webhook) {
      router.patch(`/settings/webhooks/${webhook.id}`, payload, {
        onError,
        onSuccess,
        preserveScroll: true,
      });
    } else {
      router.post("/settings/webhooks", payload, {
        onError,
        onSuccess,
        preserveScroll: true,
      });
    }
  };

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={webhook ? "Edit webhook" : "New webhook"}
      size="lg"
    >
      <Stack>
        <TextInput
          label="Name"
          value={name}
          onChange={(e) => setName(e.currentTarget.value)}
          error={errors.name}
        />
        <TextInput
          label="URL"
          placeholder="https://example.com/hooks/envault"
          value={url}
          onChange={(e) => setUrl(e.currentTarget.value)}
          error={errors.url}
        />
        <Switch
          label="Active"
          checked={active}
          onChange={(e) => setActive(e.currentTarget.checked)}
        />

        <div>
          <Text size="sm" fw={500} mb={4}>
            Events
          </Text>
          <Stack gap={4}>
            {availableEvents.map((e) => (
              <Checkbox
                key={e}
                label={e}
                checked={events.includes(e)}
                onChange={() => toggleEvent(e)}
              />
            ))}
          </Stack>
          {errors.events && (
            <Text size="xs" c="red" mt={4}>
              {errors.events}
            </Text>
          )}
        </div>

        <div>
          <Text size="sm" fw={500} mb={4}>
            Scope
          </Text>
          <Text size="xs" c="dimmed" mb={6}>
            Fire this webhook for events affecting any of the selected scopes
            (union).
          </Text>

          <Checkbox
            mb="sm"
            label="All current and future apps"
            description="Fire for every app — including apps created later."
            checked={allApps}
            onChange={(e) => setAllApps(e.currentTarget.checked)}
          />

          {!allApps && (
            <Stack gap="md">
              <div>
                <Text size="xs" fw={500} c="dimmed" mb={4}>
                  Environment Types
                </Text>
                <Stack gap={2}>
                  {scopeOptions.environment_types.map((t) => (
                    <Checkbox
                      key={t.id}
                      label={t.name}
                      checked={isSelected("environment_type", t.id)}
                      onChange={() => toggleScope("environment_type", t.id)}
                    />
                  ))}
                </Stack>
              </div>

              <div>
                <Text size="xs" fw={500} c="dimmed" mb={4}>
                  Apps & Environments
                </Text>
                <Stack gap={4}>
                  {scopeOptions.apps.map((a) => (
                    <div key={a.id} className="border-l-2 border-border pl-3">
                      <Checkbox
                        label={<strong>{a.name}</strong>}
                        checked={isSelected("app", a.id)}
                        onChange={() => toggleScope("app", a.id)}
                      />
                      <Stack gap={2} mt={4} ml="md">
                        {a.environments.map((env) => (
                          <Checkbox
                            key={env.id}
                            size="sm"
                            label={env.label}
                            checked={isSelected("environment", env.id)}
                            onChange={() => toggleScope("environment", env.id)}
                          />
                        ))}
                      </Stack>
                    </div>
                  ))}
                </Stack>
              </div>
            </Stack>
          )}

          {errors.subscriptions && (
            <Text size="xs" c="red" mt={4}>
              {errors.subscriptions}
            </Text>
          )}
        </div>

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button onClick={submit} loading={saving}>
            {webhook ? "Save changes" : "Create webhook"}
          </Button>
        </Group>
      </Stack>
    </Modal>
  );
}

WebhooksPage.layout = {
  breadcrumbs: [{ title: "Webhooks", href: "/settings/webhooks" }],
};
