import DeleteUser from "@/components/delete-user";
import ProfileController from "@/wayfinder/actions/App/Http/Controllers/Settings/ProfileController";
import { Transition } from "@headlessui/react";
import { Form, Head, Link, usePage } from "@inertiajs/react";
import { Button, TextInput } from "@mantine/core";

import Heading from "@/components/heading";
import { edit } from "@/wayfinder/routes/profile";
import { send } from "@/wayfinder/routes/verification";

export default function Profile({
  mustVerifyEmail,
  status,
}: {
  mustVerifyEmail: boolean;
  status?: string;
}) {
  const { auth } = usePage().props;

  return (
    <>
      <Head title="Profile settings" />

      <h1 className="sr-only">Profile settings</h1>

      <div className="space-y-6">
        <Heading
          variant="small"
          title="Profile information"
          description="Update your name and email address"
        />

        <Form
          {...ProfileController.update.form()}
          options={{
            preserveScroll: true,
          }}
          className="space-y-6"
        >
          {({ processing, recentlySuccessful, errors }) => (
            <>
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <TextInput
                    id="first_name"
                    className="mt-1 block w-full"
                    label="First name"
                    name="first_name"
                    defaultValue={auth.user.first_name as string}
                    error={errors.first_name}
                    required
                    autoComplete="given-name"
                    placeholder="First name"
                  />
                </div>

                <div className="grid gap-2">
                  <TextInput
                    id="last_name"
                    className="mt-1 block w-full"
                    label="Last name"
                    name="last_name"
                    defaultValue={auth.user.last_name as string}
                    error={errors.last_name}
                    required
                    autoComplete="family-name"
                    placeholder="Last name"
                  />
                </div>
              </div>

              <div className="grid gap-2">
                <TextInput
                  id="email"
                  type="email"
                  className="mt-1 block w-full"
                  label="Email address"
                  defaultValue={auth.user.email}
                  name="email"
                  error={errors.email}
                  required
                  autoComplete="username"
                  placeholder="Email address"
                />
              </div>

              {mustVerifyEmail && auth.user.email_verified_at === null && (
                <div>
                  <p className="-mt-4 text-sm text-muted-foreground">
                    Your email address is unverified.{" "}
                    <Link
                      href={send()}
                      as="button"
                      className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                      Click here to resend the verification email.
                    </Link>
                  </p>

                  {status === "verification-link-sent" && (
                    <div className="mt-2 text-sm font-medium text-green-600">
                      A new verification link has been sent to your email
                      address.
                    </div>
                  )}
                </div>
              )}

              <div className="flex items-center gap-4">
                <Button
                  type="submit"
                  disabled={processing}
                  data-test="update-profile-button"
                >
                  Save
                </Button>

                <Transition
                  show={recentlySuccessful}
                  enter="transition ease-in-out"
                  enterFrom="opacity-0"
                  leave="transition ease-in-out"
                  leaveTo="opacity-0"
                >
                  <p className="text-sm text-neutral-600">Saved</p>
                </Transition>
              </div>
            </>
          )}
        </Form>
      </div>

      <DeleteUser />
    </>
  );
}

Profile.layout = {
  breadcrumbs: [
    {
      title: "Profile settings",
      href: edit(),
    },
  ],
};
