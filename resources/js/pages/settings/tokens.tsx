import Heading from "@/components/heading";
import {
  faCheck,
  faCopy,
  faKey,
  faPencil,
  faPlus,
  faTrash,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Head, router, usePage } from "@inertiajs/react";
import {
  ActionIcon,
  Alert,
  Button,
  Code,
  CopyButton,
  Group,
  Modal,
  Text,
  TextInput,
  Tooltip,
} from "@mantine/core";
import { useState } from "react";

type TokenData = {
  id: number;
  name: string;
  last_used_at: string | null;
  created_at: string;
};

export default function Tokens({ tokens }: { tokens: TokenData[] }) {
  const { flash } = usePage().props as any;
  const [name, setName] = useState("");
  const [saving, setSaving] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);

  const newToken = flash?.newToken as string | undefined;

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    setSaving(true);
    router.post(
      "/settings/tokens",
      { name },
      {
        onSuccess: () => {
          setName("");
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const [deleteToken, setDeleteToken] = useState<TokenData | null>(null);

  const handleDelete = () => {
    if (!deleteToken) return;
    router.delete(`/settings/tokens/${deleteToken.id}`, {
      preserveScroll: true,
      onSuccess: () => setDeleteToken(null),
    });
  };

  const [renameToken, setRenameToken] = useState<TokenData | null>(null);
  const [renameName, setRenameName] = useState("");
  const [renaming, setRenaming] = useState(false);

  const openRename = (token: TokenData) => {
    setRenameToken(token);
    setRenameName(token.name);
  };

  const handleRename = () => {
    if (!renameToken || !renameName.trim()) return;
    setRenaming(true);
    router.patch(
      `/settings/tokens/${renameToken.id}`,
      { name: renameName },
      {
        preserveScroll: true,
        onSuccess: () => {
          setRenameToken(null);
          setRenaming(false);
        },
        onError: () => setRenaming(false),
      },
    );
  };

  return (
    <>
      <Head title="API Tokens" />

      <div className="space-y-6">
        <Heading
          variant="small"
          title="API Tokens"
          description="Manage personal access tokens for the Vault API and integrations"
        />

        {/* Newly created token (shown once) */}
        {newToken && (
          <Alert
            color="green"
            title="Token created"
            icon={<FontAwesomeIcon icon={faKey} />}
          >
            <Text size="sm" mb="xs">
              Copy this token now. You won't be able to see it again.
            </Text>
            <Group gap="xs">
              <Code block className="flex-1 text-xs">
                {newToken}
              </Code>
              <CopyButton value={newToken}>
                {({ copied, copy }) => (
                  <Tooltip label={copied ? "Copied" : "Copy"}>
                    <ActionIcon
                      variant="subtle"
                      color={copied ? "teal" : "gray"}
                      onClick={copy}
                    >
                      <FontAwesomeIcon icon={copied ? faCheck : faCopy} />
                    </ActionIcon>
                  </Tooltip>
                )}
              </CopyButton>
            </Group>
          </Alert>
        )}

        {/* Token list */}
        {tokens.length > 0 && (
          <div className="overflow-hidden rounded-md border border-border">
            <ul>
              {tokens.map((token, index) => (
                <li
                  key={token.id}
                  className={
                    index > 0
                      ? "border-t border-gray-200 dark:border-gray-700"
                      : ""
                  }
                >
                  <div className="flex items-center justify-between px-4 py-3">
                    <div>
                      <Text size="sm" fw={500}>
                        {token.name}
                      </Text>
                      <Text size="xs" c="dimmed">
                        Created{" "}
                        {new Date(token.created_at).toLocaleDateString()}
                        {" · "}
                        {token.last_used_at
                          ? `Last used ${new Date(token.last_used_at).toLocaleDateString()}`
                          : "Never used"}
                      </Text>
                    </div>
                    <Group gap="xs">
                      <ActionIcon
                        variant="subtle"
                        size="sm"
                        onClick={() => openRename(token)}
                        aria-label="Rename token"
                      >
                        <FontAwesomeIcon icon={faPencil} className="size-3" />
                      </ActionIcon>
                      <ActionIcon
                        variant="subtle"
                        color="red"
                        size="sm"
                        onClick={() => setDeleteToken(token)}
                        aria-label="Revoke token"
                      >
                        <FontAwesomeIcon icon={faTrash} className="size-3" />
                      </ActionIcon>
                    </Group>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}

        {tokens.length === 0 && !newToken && (
          <Text size="sm" c="dimmed">
            No API tokens yet. Create one to use with the Vault API or
            Kubernetes integrations.
          </Text>
        )}

        {/* Create token form */}
        <form onSubmit={handleCreate} className="flex items-end gap-3">
          <TextInput
            label="Token name"
            placeholder="e.g. kubernetes-production"
            value={name}
            onChange={(e) => setName(e.currentTarget.value)}
            className="flex-1"
          />
          <Button
            type="submit"
            disabled={!name.trim()}
            loading={saving}
            leftSection={<FontAwesomeIcon icon={faPlus} />}
          >
            Create token
          </Button>
        </form>
      </div>

      {/* Rename token */}
      <Modal
        opened={!!renameToken}
        onClose={() => setRenameToken(null)}
        title="Rename token"
        centered
      >
        <form
          onSubmit={(e) => {
            e.preventDefault();
            handleRename();
          }}
        >
          <TextInput
            label="Token name"
            value={renameName}
            onChange={(e) => setRenameName(e.currentTarget.value)}
            autoFocus
          />
          <Group
            justify="flex-end"
            mt="lg"
            className="border-t border-gray-200 pt-4 dark:border-gray-700"
          >
            <Button variant="outline" onClick={() => setRenameToken(null)}>
              Cancel
            </Button>
            <Button
              type="submit"
              loading={renaming}
              disabled={!renameName.trim() || renameName === renameToken?.name}
            >
              Save
            </Button>
          </Group>
        </form>
      </Modal>

      {/* Delete token confirmation */}
      <Modal
        opened={!!deleteToken}
        onClose={() => setDeleteToken(null)}
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
              Revoke this token
            </Text>
            <Text size="sm" c="dimmed" mt={4}>
              Are you sure you want to revoke "{deleteToken?.name}"? Any
              integrations using this token will stop working immediately.
            </Text>
          </div>
        </div>
        <Group
          justify="flex-end"
          mt="lg"
          className="border-t border-gray-200 pt-4 dark:border-gray-700"
        >
          <Button variant="outline" onClick={() => setDeleteToken(null)}>
            Cancel
          </Button>
          <Button color="red" onClick={handleDelete}>
            Revoke
          </Button>
        </Group>
      </Modal>
    </>
  );
}

Tokens.layout = {
  breadcrumbs: [{ title: "API Tokens", href: "/settings/tokens" }],
};
