import TextLink from "@/components/text-link";
import { Form, Head, router, usePage } from "@inertiajs/react";
import { Button, Text, TextInput } from "@mantine/core";
import { SSOLinks } from "./sso-links";

interface EmailCodeProps {
  step: "email" | "code";
  email?: string;
}

export default function EmailCode({
  step,
  email,
  passwordAuthEnabled,
}: EmailCodeProps) {
  const { errors: pageErrors } = usePage().props;

  return (
    <>
      <Head title="Log in with email code" />

      {step === "email" && (
        <>
          <Form
            action="/auth/email-code/request"
            method="post"
            className="flex flex-col gap-6"
          >
            {({ processing, errors }) => (
              <>
                <div className="grid gap-6">
                  <div className="grid gap-2">
                    <TextInput
                      id="email"
                      type="email"
                      name="email"
                      label="Email Address"
                      error={errors.email || (pageErrors as any).email}
                      required
                      withAsterisk={false}
                      autoFocus
                      autoComplete="email"
                      placeholder="email@example.com"
                    />
                  </div>

                  <Button
                    type="submit"
                    className="w-full"
                    disabled={processing}
                    loading={processing}
                  >
                    Send code
                  </Button>
                </div>

                {passwordAuthEnabled && (
                  <div className="text-center text-sm text-muted-foreground">
                    Or{" "}
                    <TextLink href="/login/password">
                      log in with password
                    </TextLink>
                  </div>
                )}
              </>
            )}
          </Form>
          <SSOLinks />
        </>
      )}

      {step === "code" && (
        <Form
          action="/auth/email-code/verify"
          method="post"
          className="flex flex-col gap-6"
        >
          {({ processing, errors }) => (
            <>
              <Text size="sm" c="dimmed">
                We've sent a confirmation code to your email. What code did we
                send?
              </Text>

              <input type="hidden" name="email" value={email} />

              <div className="grid gap-6">
                <div className="grid gap-2">
                  <TextInput
                    id="code"
                    name="code"
                    label="Confirmation Code"
                    error={errors.code || (pageErrors as any).code}
                    required
                    withAsterisk={false}
                    autoFocus
                    autoComplete="one-time-code"
                    placeholder="Paste your code here"
                  />
                </div>

                <Button
                  type="submit"
                  className="w-full"
                  disabled={processing}
                  loading={processing}
                >
                  Confirm
                </Button>
              </div>

              <div className="flex items-center justify-center gap-4 text-sm text-muted-foreground">
                <button
                  type="button"
                  className="text-primary underline underline-offset-4 hover:no-underline"
                  onClick={() =>
                    router.post("/auth/email-code/request", { email })
                  }
                >
                  Resend code
                </button>
              </div>
            </>
          )}
        </Form>
      )}
    </>
  );
}

EmailCode.layout = {
  title: "Log in with email code",
  description: "We'll send a confirmation code to your email",
};
