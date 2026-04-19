import { AppColor } from "@/colors";
import Heading from "@/components/heading";
import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import {
  faCheck,
  faGripVertical,
  faPencil,
  faPlus,
  faTrash,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Head, router, usePage } from "@inertiajs/react";
import {
  ActionIcon,
  Badge,
  Button,
  Group,
  Modal,
  NumberInput,
  Text,
  TextInput,
  Tooltip,
  useMantineTheme,
} from "@mantine/core";
import { useEffect, useState } from "react";

type EnvironmentTypeData = {
  id: number;
  name: string;
  color: string;
  per_app_limit: number | null;
  sort_order: number;
};

const envColors: AppColor[] = [
  "gray",
  "red",
  "pink",
  "purple",
  "violet",
  "indigo",
  "blue",
  "cyan",
  "teal",
  "green",
  "lime",
  "yellow",
  "orange",
];

function ColorPicker({
  value,
  onChange,
  label,
}: {
  value: string;
  onChange: (color: string) => void;
  label?: string;
}) {
  const theme = useMantineTheme();

  return (
    <div>
      {label && (
        <Text size="sm" fw={500} mb={4}>
          {label}
        </Text>
      )}
      <div className="flex flex-wrap gap-1.5">
        {envColors.map((color) => {
          const isSelected = value === color;
          const colorValue =
            theme.colors[color]?.[5] ?? `var(--mantine-color-${color}-5)`;

          return (
            <Tooltip key={color} label={color} position="top">
              <button
                type="button"
                onClick={() => onChange(color)}
                className={`size-6 cursor-pointer rounded-md transition-all ${
                  isSelected
                    ? "ring-2 ring-primary-500 ring-offset-2"
                    : "hover:scale-110"
                }`}
                style={{ backgroundColor: colorValue }}
                aria-label={color}
              />
            </Tooltip>
          );
        })}
      </div>
    </div>
  );
}

function SortableTypeRow({
  type,
  isFirst,
  onEdit,
  onDelete,
}: {
  type: EnvironmentTypeData;
  isFirst: boolean;
  onEdit: (type: EnvironmentTypeData) => void;
  onDelete: (type: EnvironmentTypeData) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition } =
    useSortable({ id: type.id });
  const style = { transform: CSS.Transform.toString(transform), transition };

  return (
    <li
      ref={setNodeRef}
      style={style}
      className={`dark:bg-dark-700 bg-white ${!isFirst ? "border-t border-gray-200 dark:border-gray-700" : ""}`}
    >
      <div className="flex items-center justify-between px-4 py-3">
        <div className="flex items-center gap-3">
          <button
            {...attributes}
            {...listeners}
            className="cursor-grab text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            aria-label="Drag to reorder"
          >
            <FontAwesomeIcon icon={faGripVertical} className="size-4" />
          </button>
          <Badge size="sm" variant="light" color={type.color as AppColor}>
            {type.name}
          </Badge>
          <Text size="xs" c="dimmed">
            {type.per_app_limit === 1
              ? "1 per app"
              : type.per_app_limit
                ? `${type.per_app_limit} per app`
                : "Unlimited"}
          </Text>
        </div>
        <Group gap={4}>
          <ActionIcon variant="subtle" size="sm" onClick={() => onEdit(type)}>
            <FontAwesomeIcon icon={faPencil} className="size-3" />
          </ActionIcon>
          <ActionIcon
            variant="subtle"
            color="red"
            size="sm"
            onClick={() => onDelete(type)}
          >
            <FontAwesomeIcon icon={faTrash} className="size-3" />
          </ActionIcon>
        </Group>
      </div>
    </li>
  );
}

export default function EnvironmentTypes({
  types: serverTypes,
}: {
  types: EnvironmentTypeData[];
}) {
  const { errors: pageErrors } = usePage().props;
  const [localTypes, setLocalTypes] = useState(serverTypes);
  const types = localTypes;

  // Sync with server when props change
  useEffect(() => {
    setLocalTypes(serverTypes);
  }, [serverTypes]);

  const [saving, setSaving] = useState(false);
  const [newName, setNewName] = useState("");
  const [newColor, setNewColor] = useState("gray");
  const [newLimit, setNewLimit] = useState<number | "">("");
  const [editType, setEditType] = useState<EnvironmentTypeData | null>(null);
  const [editName, setEditName] = useState("");
  const [editColor, setEditColor] = useState("");
  const [editLimit, setEditLimit] = useState<number | "">("");
  const [deleteType, setDeleteType] = useState<EnvironmentTypeData | null>(
    null,
  );

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = types.findIndex((t) => t.id === active.id);
    const newIndex = types.findIndex((t) => t.id === over.id);
    const reordered = arrayMove(types, oldIndex, newIndex);
    setLocalTypes(reordered);
    router.post(
      "/settings/environments/reorder",
      {
        ids: reordered.map((t) => t.id),
      },
      { preserveScroll: true, preserveState: true },
    );
  };

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName.trim()) return;
    setSaving(true);
    router.post(
      "/settings/environments",
      {
        name: newName,
        color: newColor,
        per_app_limit: newLimit || null,
      },
      {
        onSuccess: () => {
          setNewName("");
          setNewColor("gray");
          setNewLimit("");
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleUpdate = () => {
    if (!editType) return;
    setSaving(true);
    router.patch(
      `/settings/environments/${editType.id}`,
      {
        name: editName,
        color: editColor,
        per_app_limit: editLimit || null,
      },
      {
        onSuccess: () => {
          setEditType(null);
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleDelete = () => {
    if (!deleteType) return;
    setSaving(true);
    router.delete(`/settings/environments/${deleteType.id}`, {
      onSuccess: () => {
        setDeleteType(null);
        setSaving(false);
      },
      onError: () => setSaving(false),
      preserveScroll: true,
    });
  };

  const openEdit = (type: EnvironmentTypeData) => {
    setEditName(type.name);
    setEditColor(type.color);
    setEditLimit(type.per_app_limit ?? "");
    setEditType(type);
  };

  return (
    <>
      <Head title="Environment Types" />
      <div className="space-y-6">
        <Heading
          variant="small"
          title="Environment Types"
          description="Define global environment types that can be added to apps"
        />

        {/* Types list */}
        {types.length > 0 && (
          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
          >
            <SortableContext
              items={types.map((t) => t.id)}
              strategy={verticalListSortingStrategy}
            >
              <div className="overflow-hidden rounded-md border border-border">
                <ul>
                  {types.map((type, index) => (
                    <SortableTypeRow
                      key={type.id}
                      type={type}
                      isFirst={index === 0}
                      onEdit={openEdit}
                      onDelete={setDeleteType}
                    />
                  ))}
                </ul>
              </div>
            </SortableContext>
          </DndContext>
        )}

        {/* Add type form */}
        <form onSubmit={handleCreate} className="space-y-4">
          <div className="flex items-end gap-3">
            <TextInput
              label="Name"
              placeholder="Production"
              value={newName}
              onChange={(e) => setNewName(e.currentTarget.value)}
              className="flex-1"
            />
            <NumberInput
              label="Limit per app"
              placeholder="∞"
              min={1}
              value={newLimit}
              onChange={(v) => setNewLimit(v as number | "")}
              w={120}
            />
          </div>
          <ColorPicker label="Color" value={newColor} onChange={setNewColor} />
          <Button
            type="submit"
            disabled={!newName.trim()}
            loading={saving}
            leftSection={<FontAwesomeIcon icon={faPlus} />}
          >
            Add
          </Button>
        </form>
      </div>

      {/* Edit Modal */}
      <Modal
        opened={!!editType}
        onClose={() => setEditType(null)}
        title={`Edit ${editType?.name}`}
      >
        <div className="space-y-4">
          <TextInput
            label="Name"
            value={editName}
            onChange={(e) => setEditName(e.currentTarget.value)}
            required
          />
          <ColorPicker
            label="Color"
            value={editColor}
            onChange={setEditColor}
          />
          <NumberInput
            label="Limit per app"
            placeholder="Unlimited"
            min={1}
            value={editLimit}
            onChange={(v) => setEditLimit(v as number | "")}
          />
          <Group justify="flex-end">
            <Button variant="outline" onClick={() => setEditType(null)}>
              Cancel
            </Button>
            <Button
              onClick={handleUpdate}
              loading={saving}
              leftSection={<FontAwesomeIcon icon={faCheck} />}
            >
              Save
            </Button>
          </Group>
        </div>
      </Modal>

      {/* Delete Modal */}
      <Modal
        opened={!!deleteType}
        onClose={() => setDeleteType(null)}
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
              Delete this environment type
            </Text>
            <Text size="sm" c="dimmed" mt={4}>
              Are you sure you want to delete "{deleteType?.name}"? All apps
              using this type must be reclassified first.
            </Text>
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
          <Button variant="outline" onClick={() => setDeleteType(null)}>
            Cancel
          </Button>
          <Button color="red" onClick={handleDelete} loading={saving}>
            Confirm
          </Button>
        </Group>
      </Modal>
    </>
  );
}

EnvironmentTypes.layout = {
  breadcrumbs: [{ title: "Environment Types", href: "/settings/environments" }],
};
