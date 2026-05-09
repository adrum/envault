import SecurityController from "@/wayfinder/actions/App/Http/Controllers/Settings/SecurityController";
import PasskeyRegistrationController from "@/wayfinder/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController";
import { router, useForm, usePage } from "@inertiajs/react";
import { usePasskeyRegister } from "@laravel/passkeys/react";
import {
  Button,
  InputError,
  Text,
  TextInput,
  useComputedColorScheme,
} from "@mantine/core";
import { modals } from "@mantine/modals";
import { notifications } from "@mantine/notifications";
import dayjs from "dayjs";
import duration from "dayjs/plugin/duration";
import relativeTime from "dayjs/plugin/relativeTime";
import Heading from "./heading";

dayjs.extend(duration);
dayjs.extend(relativeTime);

export default function PasskeysForm() {
  const page = usePage<{ passkeys: any[] }>();

  const form = useForm({
    name: "",
  });

  const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
      form.reset();
      notifications.show({
        title: "Success",
        message: "Passkey created successfully",
        color: "green",
      });
      router.reload({ only: ["passkeys"] });
    },
    onError: () => {
      notifications.show({
        title: "Error",
        message: "Failed to create passkey",
        color: "red",
      });
    },
  });

  const isDark = useComputedColorScheme() === "dark";

  const handleRenamePasskey = async (passkey: any) => {
    modals.openConfirmModal({
      title: "Rename passkey",
      centered: true,
      children: (
        <>
          <Text size="sm">
            Enter the new name for this passkey named {passkey.name}.
          </Text>
          <TextInput
            size="sm"
            className="mt-2"
            required
            label="New name"
            name="new_name"
          />
        </>
      ),
      labels: { confirm: "Rename passkey", cancel: "Cancel" },
      onCancel: () => console.log("Cancel"),
      onConfirm: () => {
        const newName = (
          document.querySelector("input[name='new_name']") as HTMLInputElement
        )?.value;
        if (!newName) {
          notifications.show({
            title: "Error",
            message: "Please enter a new name",
            color: "red",
          });
          return;
        }
        router.put(
          SecurityController.updatePasskey(passkey.id),
          {
            name: newName,
          },
          {
            onSuccess: () => {
              modals.closeAll();
            },
          },
        );
      },
    });
  };

  const handleDeletePasskey = async (passkey: any) => {
    modals.openConfirmModal({
      title: "Delete passkey",
      centered: true,
      children: (
        <>
          <Text size="sm">
            Enter DELETE to confirm deletion of this passkey named{" "}
            {passkey.name}.
          </Text>
          <TextInput
            size="sm"
            className="mt-2"
            placeholder="DELETE"
            required
            label="Confirmation"
            name="confirmation"
          />
        </>
      ),
      labels: { confirm: "Delete passkey", cancel: "Cancel" },
      onCancel: () => console.log("Cancel"),
      onConfirm: () => {
        const newName = (
          document.querySelector(
            "input[name='confirmation']",
          ) as HTMLInputElement
        )?.value;
        if (!newName || newName !== "DELETE") {
          notifications.show({
            title: "Error",
            message: "Please enter DELETE to confirm deletion",
            color: "red",
          });
          return;
        }
        router.delete(PasskeyRegistrationController.destroy(passkey.id), {
          onSuccess: () => {
            modals.closeAll();
            notifications.show({
              title: "Success",
              message: "Passkey deleted successfully",
              color: "green",
            });
          },
        });
      },
    });
  };

  if (!page.props.auth.user || !page.props.canManagePasskeys) {
    return null;
  }

  return (
    <div>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          register(form.data.name);
        }}
      >
        <div className="max-w-xl text-sm">
          <div className="">
            <Heading
              variant="small"
              title="Your Passkeys"
              description={
                !page.props.passkeys || page.props.passkeys?.length == 0
                  ? "Create your first passkey to get started."
                  : "Manage your passkeys for a modern authentication experience."
              }
            />

            <div className="mt-2 flex flex-col gap-y-4 divide-y">
              {page.props.passkeys?.map((passkey) => (
                <div
                  key={passkey.id}
                  className="flex items-center justify-between py-4"
                >
                  <div className="flex items-center gap-x-2">
                    {(isDark
                      ? passkey.authenticator_icon_dark
                      : passkey.authenticator_icon_light) && (
                      <img
                        src={
                          isDark
                            ? passkey.authenticator_icon_dark
                            : passkey.authenticator_icon_light
                        }
                        alt={passkey.authenticator}
                        className="h-6 w-6 rounded-full"
                      />
                    )}
                    <div className="flex flex-col">
                      <div className="font-semibold">
                        {passkey.name}
                        <span className="ml-2 text-xs font-medium text-muted-foreground">
                          - {passkey.authenticator}
                        </span>
                      </div>
                      <div className="text-sm font-thin text-muted-foreground">
                        {dayjs(passkey.created_at).fromNow()}
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center gap-x-2">
                    <div className="ml-2">
                      <Button
                        variant="default"
                        size="xs"
                        type="button"
                        className="text-sm font-medium"
                        onClick={() => handleRenamePasskey(passkey)}
                      >
                        Rename
                      </Button>
                    </div>
                    <div className="ml-2">
                      <Button
                        color="red"
                        size="xs"
                        type="button"
                        className="text-sm font-medium"
                        onClick={() => handleDeletePasskey(passkey)}
                      >
                        Delete
                      </Button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
        <div className="mt-2 flex items-end gap-4">
          <TextInput
            name="name"
            label="New Passkey"
            placeholder="Passkey Name"
            required
            value={form.data.name}
            onChange={(e) => form.setData("name", e.target.value)}
          />
          <div>
            <Button loading={isLoading} type="submit" disabled={!isSupported}>
              {isSupported
                ? "Create Passkey"
                : "Passkey creation not supported"}
            </Button>
          </div>
        </div>
        <InputError className="mt-2">{error}</InputError>
      </form>
    </div>
  );
}
