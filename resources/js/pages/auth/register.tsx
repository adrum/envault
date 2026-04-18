import TextLink from "@/components/text-link";
import { login } from "@/wayfinder/routes";
import { Form, Head } from "@inertiajs/react";
import { Button, PasswordInput, TextInput } from "@mantine/core";

export default function Register() {
  return (
    <>
      <Head title="Register" />
      <Form
        action="/register"
        method="post"
        resetOnSuccess={["password", "password_confirmation"]}
        disableWhileProcessing
        className="flex flex-col gap-6"
      >
        {({ processing, errors }) => (
          <>
            <div className="grid gap-6">
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <TextInput
                    id="first_name"
                    type="text"
                    name="first_name"
                    required
                    withAsterisk={false}
                    autoFocus
                    tabIndex={1}
                    label="First name"
                    error={errors.first_name}
                    autoComplete="given-name"
                    disabled={processing}
                    placeholder="First name"
                  />
                </div>

                <div className="grid gap-2">
                  <TextInput
                    id="last_name"
                    type="text"
                    name="last_name"
                    required
                    withAsterisk={false}
                    tabIndex={2}
                    label="Last name"
                    error={errors.last_name}
                    autoComplete="family-name"
                    disabled={processing}
                    placeholder="Last name"
                  />
                </div>
              </div>

              <div className="grid gap-2">
                <TextInput
                  id="email"
                  type="email"
                  name="email"
                  required
                  withAsterisk={false}
                  tabIndex={3}
                  label="Email address"
                  error={errors.email}
                  autoComplete="email"
                  disabled={processing}
                  placeholder="email@example.com"
                />
              </div>

              <div className="grid gap-2">
                <PasswordInput
                  id="password"
                  name="password"
                  required
                  withAsterisk={false}
                  tabIndex={4}
                  label="Password"
                  error={errors.password}
                  autoComplete="new-password"
                  disabled={processing}
                  placeholder="Password"
                />
              </div>

              <div className="grid gap-2">
                <PasswordInput
                  id="password_confirmation"
                  name="password_confirmation"
                  required
                  withAsterisk={false}
                  tabIndex={5}
                  label="Confirm password"
                  error={errors.password_confirmation}
                  autoComplete="new-password"
                  disabled={processing}
                  placeholder="Confirm password"
                />
              </div>

              <Button
                type="submit"
                className="mt-2 w-full"
                tabIndex={6}
                loading={processing}
                disabled={processing}
                data-test="register-user-button"
              >
                Create account
              </Button>
            </div>

            <div className="text-center text-sm text-muted-foreground">
              Already have an account?{" "}
              <TextLink href={login()} tabIndex={7}>
                Log in
              </TextLink>
            </div>
          </>
        )}
      </Form>
    </>
  );
}

Register.layout = {
  title: "Create an account",
  description: "Enter your details below to create your account",
};
