import { usePage } from "@inertiajs/react";
import { usePasskeyVerify } from "@laravel/passkeys/react";
import { Button } from "@mantine/core";

export default function PasskeyButton() {
  const page = usePage();
  const { verify, isLoading, error, isSupported } = usePasskeyVerify({
    autofill: false,
    onSuccess: (response) => {
      if (response.redirect) {
        window.location.href = response.redirect;
      }
    },
  });

  return (
    <>
      {page.props.canUsePasskeys && isSupported && (
        <>
          <Button
            loading={isLoading}
            variant="outline"
            type="button"
            onClick={() => verify()}
          >
            Login Using Passkey
          </Button>
          {error && <p className="error">{error}</p>}
        </>
      )}
    </>
  );
}
