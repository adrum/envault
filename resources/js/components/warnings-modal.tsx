import { Alert, Button, Code, Group, Modal, Stack, Text } from "@mantine/core";

export type Warning = {
  message: string;
  keys: string[];
};

type Props = {
  opened: boolean;
  warnings: Warning[];
  onCancel: () => void;
  onConfirm: () => void;
  loading?: boolean;
};

export function WarningsModal({
  opened,
  warnings,
  onCancel,
  onConfirm,
  loading,
}: Props) {
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

        {warnings.map((warning, idx) => (
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
            </Stack>
          </Alert>
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
