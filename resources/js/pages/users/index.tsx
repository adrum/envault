import {
  faCheck,
  faChevronLeft,
  faEnvelope,
  faPencil,
  faTrash,
} from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Head, router, usePage } from "@inertiajs/react";
import {
  ActionIcon,
  Button,
  Group,
  Modal,
  Select,
  Text,
  TextInput,
} from "@mantine/core";
import { useState } from "react";

type UserData = {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  role: string;
  last_login_at: string | null;
};

type ModalMode = "view" | "edit" | null;

export default function UsersIndex({ users }: { users: UserData[] }) {
  const { auth } = usePage().props as any;
  const [saving, setSaving] = useState(false);

  // Create form
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");

  // View/Edit modal
  const [selectedUser, setSelectedUser] = useState<UserData | null>(null);
  const [modalMode, setModalMode] = useState<ModalMode>(null);
  const [editFirstName, setEditFirstName] = useState("");
  const [editLastName, setEditLastName] = useState("");
  const [editEmail, setEditEmail] = useState("");
  const [editRole, setEditRole] = useState("");

  // Delete
  const [deleteUser, setDeleteUser] = useState<UserData | null>(null);

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!firstName.trim() || !lastName.trim() || !email.trim()) return;
    setSaving(true);
    router.post(
      "/users",
      { first_name: firstName, last_name: lastName, email },
      {
        onSuccess: () => {
          setFirstName("");
          setLastName("");
          setEmail("");
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const openViewModal = (user: UserData) => {
    setSelectedUser(user);
    setEditFirstName(user.first_name);
    setEditLastName(user.last_name);
    setEditEmail(user.email);
    setEditRole(user.role);
    setModalMode("view");
  };

  const switchToEdit = () => setModalMode("edit");

  const switchToView = () => {
    if (selectedUser) {
      setEditFirstName(selectedUser.first_name);
      setEditLastName(selectedUser.last_name);
      setEditEmail(selectedUser.email);
      setEditRole(selectedUser.role);
    }
    setModalMode("view");
  };

  const closeModal = () => {
    setSelectedUser(null);
    setModalMode(null);
  };

  const handleEdit = () => {
    if (!selectedUser) return;
    setSaving(true);
    router.patch(
      `/users/${selectedUser.id}`,
      {
        first_name: editFirstName,
        last_name: editLastName,
        email: editEmail,
        role: editRole,
      },
      {
        onSuccess: () => {
          closeModal();
          setSaving(false);
        },
        onError: () => setSaving(false),
        preserveScroll: true,
      },
    );
  };

  const handleDelete = () => {
    if (!deleteUser) return;
    setSaving(true);
    router.delete(`/users/${deleteUser.id}`, {
      onSuccess: () => {
        setDeleteUser(null);
        closeModal();
        setSaving(false);
      },
      onError: () => setSaving(false),
      preserveScroll: true,
    });
  };

  return (
    <>
      <Head title="Users" />

      {/* New user inline form */}
      <div className="mb-6 overflow-hidden rounded-md bg-background shadow">
        <div className="px-4 py-5 sm:px-6">
          <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
            New user
          </h3>
        </div>
        <form onSubmit={handleCreate}>
          <div className="border-t border-gray-200 px-4 py-5 sm:px-6 dark:border-gray-700">
            <div className="grid grid-cols-3 items-center gap-4 border-b border-gray-100 pb-5 dark:border-gray-800">
              <label className="text-sm font-bold text-gray-700 dark:text-gray-300">
                First name
              </label>
              <div className="col-span-2">
                <TextInput
                  placeholder="Tom"
                  value={firstName}
                  onChange={(e) => setFirstName(e.currentTarget.value)}
                  className="max-w-sm"
                />
              </div>
            </div>
            <div className="grid grid-cols-3 items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
              <label className="text-sm font-bold text-gray-700 dark:text-gray-300">
                Last name
              </label>
              <div className="col-span-2">
                <TextInput
                  placeholder="Cook"
                  value={lastName}
                  onChange={(e) => setLastName(e.currentTarget.value)}
                  className="max-w-sm"
                />
              </div>
            </div>
            <div className="grid grid-cols-3 items-center gap-4 pt-5">
              <label className="text-sm font-bold text-gray-700 dark:text-gray-300">
                Email address
              </label>
              <div className="col-span-2">
                <TextInput
                  type="email"
                  placeholder="tom@example.com"
                  value={email}
                  onChange={(e) => setEmail(e.currentTarget.value)}
                />
              </div>
            </div>
          </div>
          <div className="flex justify-end border-t border-gray-200 px-4 py-4 sm:px-6 dark:border-gray-700">
            <Button
              type="submit"
              loading={saving}
              disabled={!firstName.trim() || !lastName.trim() || !email.trim()}
              leftSection={<FontAwesomeIcon icon={faCheck} />}
            >
              Create
            </Button>
          </div>
        </form>
      </div>

      {/* User list */}
      <div className="overflow-hidden rounded-md bg-background shadow">
        <ul>
          {(users || []).map((user, index) => (
            <li
              key={user.id}
              className={
                index > 0 ? "border-t border-gray-200 dark:border-gray-700" : ""
              }
            >
              <button
                type="button"
                onClick={() => openViewModal(user)}
                className="flex w-full items-center justify-between px-4 py-4 text-left transition-colors duration-150 hover:bg-gray-50 focus:bg-gray-100 focus:outline-none sm:px-6 dark:hover:bg-gray-800 dark:focus:bg-gray-800"
              >
                <div className="min-w-0 flex-1">
                  <Text size="sm" fw={500} className="text-primary!">
                    {user.first_name} {user.last_name}
                  </Text>
                  <div className="mt-1 flex items-center gap-1.5">
                    <FontAwesomeIcon
                      icon={faEnvelope}
                      className="size-4 text-gray-400"
                    />
                    <Text size="sm" c="dimmed">
                      {user.email}
                    </Text>
                  </div>
                </div>
                {(user.role === "owner" || user.role === "admin") && (
                  <Text size="sm" c="dimmed" tt="capitalize">
                    {user.role}
                  </Text>
                )}
              </button>
            </li>
          ))}
        </ul>
      </div>

      {/* User View/Edit Modal */}
      <Modal
        opened={!!selectedUser && !!modalMode}
        onClose={closeModal}
        withCloseButton={false}
        size="lg"
      >
        {selectedUser && modalMode === "view" && (
          <>
            <Text size="xl" fw={700} mb="md">
              {selectedUser.first_name} {selectedUser.last_name}
            </Text>
            <div className="border-t border-gray-200 dark:border-gray-700">
              <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  First name
                </label>
                <TextInput
                  value={selectedUser.first_name}
                  readOnly
                  className="flex-1"
                  variant="filled"
                />
              </div>
              <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Last name
                </label>
                <TextInput
                  value={selectedUser.last_name}
                  readOnly
                  className="flex-1"
                  variant="filled"
                />
              </div>
              <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Email
                </label>
                <TextInput
                  value={selectedUser.email}
                  readOnly
                  className="flex-1"
                  variant="filled"
                />
              </div>
              <div className="flex items-center gap-4 py-5">
                <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Role
                </label>
                <Select
                  data={[
                    { value: "user", label: "User" },
                    { value: "admin", label: "Admin" },
                    { value: "owner", label: "Owner" },
                  ]}
                  value={selectedUser.role}
                  readOnly
                  className="flex-1"
                  variant="filled"
                />
              </div>
            </div>
            <div className="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
              <Button variant="outline" onClick={closeModal}>
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

        {selectedUser && modalMode === "edit" && (
          <>
            <Text size="xl" fw={700} mb="md">
              Edit {selectedUser.first_name} {selectedUser.last_name}
            </Text>
            <div className="border-t border-gray-200 dark:border-gray-700">
              <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  First name
                </label>
                <TextInput
                  value={editFirstName}
                  onChange={(e) => setEditFirstName(e.currentTarget.value)}
                  className="flex-1"
                />
              </div>
              <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Last name
                </label>
                <TextInput
                  value={editLastName}
                  onChange={(e) => setEditLastName(e.currentTarget.value)}
                  className="flex-1"
                />
              </div>
              <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Email
                </label>
                <TextInput
                  value={editEmail}
                  onChange={(e) => setEditEmail(e.currentTarget.value)}
                  className="flex-1"
                />
              </div>
              <div className="flex items-start gap-4 py-5">
                <label className="mt-2 w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                  Role
                </label>
                <div className="flex-1">
                  <Select
                    data={[
                      { value: "user", label: "User" },
                      { value: "admin", label: "Admin" },
                      { value: "owner", label: "Owner" },
                    ]}
                    value={editRole}
                    onChange={(v) => v && setEditRole(v)}
                  />
                  <Text size="xs" c="dimmed" mt={6}>
                    {editRole === "owner"
                      ? "Owners have no restrictions and can manage anything on your server."
                      : editRole === "admin"
                        ? "Admins can manage every app on your server, and can create new users."
                        : 'Users can\'t access any apps by default, instead being added as a "collaborator" to their apps.'}
                  </Text>
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
                {selectedUser.id !== auth.user.id && (
                  <ActionIcon
                    variant="filled"
                    color="red"
                    size="lg"
                    onClick={() => setDeleteUser(selectedUser)}
                    aria-label="Delete user"
                  >
                    <FontAwesomeIcon icon={faTrash} />
                  </ActionIcon>
                )}
              </Group>
              <Button
                onClick={handleEdit}
                loading={saving}
                leftSection={<FontAwesomeIcon icon={faCheck} />}
              >
                Save
              </Button>
            </div>
          </>
        )}
      </Modal>

      {/* Delete User Confirmation */}
      <Modal
        opened={!!deleteUser}
        onClose={() => setDeleteUser(null)}
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
              Delete this user
            </Text>
            <Text size="sm" c="dimmed" mt={4}>
              Are you sure you want to delete {deleteUser?.first_name}{" "}
              {deleteUser?.last_name}? This action is permanent.
            </Text>
          </div>
        </div>
        <Group
          justify="flex-end"
          mt="lg"
          className="border-t border-gray-200 pt-4 dark:border-gray-700"
        >
          <Button variant="outline" onClick={() => setDeleteUser(null)}>
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

UsersIndex.layout = {
  breadcrumbs: [{ title: "Users", href: "/users" }],
};
