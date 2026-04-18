import AppLogoIcon from "@/components/app-logo-icon";
import { faCheck } from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { Form, Head, usePage } from "@inertiajs/react";
import { Button, PasswordInput, Text, TextInput } from "@mantine/core";

export default function Setup() {
  const { features } = usePage<{
    features: { passwordAuthentication: boolean };
  }>().props;
  return (
    <>
      <Head title="Setup" />
      <div className="flex min-h-screen flex-col items-center justify-center bg-gray-800 px-4">
        <div className="mb-8 flex flex-col items-center">
          <AppLogoIcon
            className="mb-4 size-16"
            style={{
              filter:
                "brightness(0) saturate(100%) invert(100%) sepia(0%) saturate(0%) hue-rotate(0deg)",
            }}
          />
          <Text size="sm" c="gray.4">
            Get started by providing details for the owner's account.
          </Text>
          <Text size="xs" c="gray.5">
            Owners have no restrictions and can manage anything on your server.
          </Text>
        </div>

        <div className="w-full max-w-md overflow-hidden rounded-md bg-white shadow dark:bg-gray-900">
          <div className="px-6 py-5">
            <h3 className="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100">
              Create owner account
            </h3>
          </div>
          <Form
            action="/setup"
            method="post"
            className="border-t border-gray-200 dark:border-gray-700"
          >
            {({ processing, errors }) => (
              <>
                <div className="space-y-0 px-6 py-5">
                  <div className="flex items-center gap-4 border-b border-gray-100 pb-5 dark:border-gray-800">
                    <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                      First name
                    </label>
                    <TextInput
                      name="first_name"
                      placeholder="Austin"
                      error={errors.first_name}
                      className="flex-1"
                      required
                      autoFocus
                    />
                  </div>
                  <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                    <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                      Last name
                    </label>
                    <TextInput
                      name="last_name"
                      placeholder="Drummond"
                      error={errors.last_name}
                      className="flex-1"
                      required
                    />
                  </div>
                  <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                    <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                      Email
                    </label>
                    <TextInput
                      name="email"
                      type="email"
                      placeholder="austin@example.com"
                      error={errors.email}
                      className="flex-1"
                      required
                    />
                  </div>
                  {features.passwordAuthentication && (
                    <>
                      <div className="flex items-center gap-4 border-b border-gray-100 py-5 dark:border-gray-800">
                        <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                          Password
                        </label>
                        <PasswordInput
                          name="password"
                          placeholder="Password"
                          error={errors.password}
                          className="flex-1"
                          required
                        />
                      </div>
                      <div className="flex items-center gap-4 pt-5">
                        <label className="w-28 shrink-0 text-sm font-bold text-gray-700 dark:text-gray-300">
                          Confirm
                        </label>
                        <PasswordInput
                          name="password_confirmation"
                          placeholder="Confirm password"
                          className="flex-1"
                          required
                        />
                      </div>
                    </>
                  )}
                </div>
                <div className="flex justify-end border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                  <Button
                    type="submit"
                    loading={processing}
                    leftSection={<FontAwesomeIcon icon={faCheck} />}
                  >
                    Create Account
                  </Button>
                </div>
              </>
            )}
          </Form>
        </div>
      </div>
    </>
  );
}
