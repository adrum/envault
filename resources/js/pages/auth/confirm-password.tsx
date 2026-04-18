import { store } from "@/wayfinder/routes/password/confirm";
import { Form, Head, setLayoutProps, usePage } from "@inertiajs/react";
import { Button, PasswordInput } from "@mantine/core";

export default function ConfirmPassword() {
  const { auth } = usePage().props;

  const content = {
    title: "Confirm your password",
    description:
      "This is a secure area of the application. Please confirm your password before continuing.",
    action: "Confirm password",
    inputLabel: "Password",
  };

  if (!auth.user.has_password) {
    content.title = "Confirm action";
    content.description =
      "This is a secure area of the application. Please type 'confirm' to continue.";
    content.action = "Confirm action";
    content.inputLabel = "Confirm";
  }

  setLayoutProps({
    title: content.title,
    description: content.description,
  });

  return (
    <>
      <Head title={content.action} />

      <Form {...store.form()} resetOnSuccess={["password"]}>
        {({ processing, errors }) => (
          <div className="space-y-6">
            <div className="grid gap-2">
              <PasswordInput
                id="password"
                name="password"
                label={content.inputLabel}
                error={errors.password}
                placeholder={content.inputLabel}
                autoComplete="current-password"
                autoFocus
              />
            </div>

            <div className="flex items-center">
              <Button
                type="submit"
                className="w-full"
                loading={processing}
                disabled={processing}
                data-test="confirm-password-button"
              >
                {content.action}
              </Button>
            </div>
          </div>
        )}
      </Form>
    </>
  );
}

ConfirmPassword.layout = {
  title: "Confirm your password",
  description:
    "This is a secure area of the application. Please confirm your password before continuing.",
};
