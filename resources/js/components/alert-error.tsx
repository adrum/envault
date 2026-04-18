import { faCircleExclamation } from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Alert, List } from "@mantine/core";

export default function AlertError({
  errors,
  title,
}: {
  errors: string[];
  title?: string;
}) {
  return (
    <Alert
      icon={<FontAwesomeIcon icon={faCircleExclamation} className="size-4" />}
      title={title || "Something went wrong."}
      color="red"
      variant="light"
    >
      <List size="sm" spacing="xs">
        {Array.from(new Set(errors)).map((error, index) => (
          <List.Item key={index}>{error}</List.Item>
        ))}
      </List>
    </Alert>
  );
}
