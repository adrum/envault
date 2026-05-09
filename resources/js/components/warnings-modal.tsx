import {
  Alert,
  Badge,
  Button,
  Code,
  Group,
  Modal,
  Stack,
  Text,
} from "@mantine/core";

export type Warning = {
  message: string;
  keys: string[];
  framework: string | null;
};

type Props = {
  opened: boolean;
  warnings: Warning[];
  onCancel: () => void;
  onConfirm: () => void;
  onGenerateAppKey?: () => void;
  loading?: boolean;
};

const FRAMEWORK_LABELS: Record<string, string> = {
  laravel: "Laravel",
  dotnet: ".NET",
};

const GENERAL_GROUP = "__general__";

function groupLabel(group: string): string {
  if (group === GENERAL_GROUP) {
    return "General";
  }

  return FRAMEWORK_LABELS[group] ?? group;
}

function groupWarnings(warnings: Warning[]): Array<[string, Warning[]]> {
  const groups = new Map<string, Warning[]>();

  for (const warning of warnings) {
    const key = warning.framework ?? GENERAL_GROUP;
    const existing = groups.get(key);
    if (existing) {
      existing.push(warning);
    } else {
      groups.set(key, [warning]);
    }
  }

  return Array.from(groups.entries());
}

export function WarningsModal({
  opened,
  warnings,
  onCancel,
  onConfirm,
  onGenerateAppKey,
  loading,
}: Props) {
  const grouped = groupWarnings(warnings);

  return (
    <Modal
      opened={opened}
      onClose={onCancel}
      title="Review warnings before saving"
      size="lg"
    >
      <Stack>
        <Text size="sm" c="dimmed">
          We noticed potential issues with the values you&apos;re about to save.
          You can still proceed, but please review them first.
        </Text>

        {grouped.map(([group, groupWarnings]) => (
          <Stack key={group} gap="xs">
            <Group gap="xs">
              <Text size="sm" fw={600}>
                {groupLabel(group)}
              </Text>
              <Badge size="sm" variant="light" color="gray">
                {groupWarnings.length}
              </Badge>
              {group !== GENERAL_GROUP && (
                <Text size="xs" c="dimmed">
                  Detected automatically from your variables
                </Text>
              )}
            </Group>

            {groupWarnings.map((warning, idx) => {
              const isAppKeyEmpty =
                !!onGenerateAppKey &&
                warning.keys.length === 1 &&
                warning.keys[0] === "APP_KEY" &&
                /APP_KEY is empty/.test(warning.message);

              return (
                <Alert key={idx} color="yellow" variant="light">
                  <Stack gap="xs">
                    <Text size="sm">{warning.message}</Text>
                    {warning.keys.length > 0 && (
                      <Group gap="xs">
                        {warning.keys.map((k) => (
                          <Code key={k}>{k}</Code>
                        ))}
                      </Group>
                    )}
                    {isAppKeyEmpty && (
                      <Group>
                        <Button
                          size="xs"
                          variant="light"
                          onClick={onGenerateAppKey}
                        >
                          Generate one
                        </Button>
                      </Group>
                    )}
                  </Stack>
                </Alert>
              );
            })}
          </Stack>
        ))}

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={onCancel} disabled={loading}>
            Go back
          </Button>
          <Button color="yellow" onClick={onConfirm} loading={loading}>
            Save anyway
          </Button>
        </Group>
      </Stack>
    </Modal>
  );
}
