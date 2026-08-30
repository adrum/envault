import { useFormContext, usePage } from "@inertiajs/react";

type LaravelPassportSso = false | { label: string; logo: string | null };
type OidcSso = { name: string; label: string; logo: string | null };

const SSOButton = ({
  href,
  label,
  logo,
}: {
  href: string;
  label: string;
  logo: string | null;
}) => (
  <a
    href={href}
    className="flex w-full items-center justify-center gap-3 rounded-md border border-solid border-gray-300 bg-white px-3 py-1.5 text-[#24292F] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#24292F] dark:bg-background"
  >
    <span className="flex h-10 w-full items-center justify-center gap-2">
      {logo && (
        <img
          src={logo}
          alt=""
          className="h-6 w-6 object-contain"
          aria-hidden="true"
        />
      )}
      <span className="mb-0.5 truncate text-sm leading-tight font-semibold text-foreground">
        {label}
      </span>
    </span>
  </a>
);

export const SSOLinks = () => {
  const form = useFormContext();
  const remember = form?.getData().remember ?? false;
  const { features } = usePage<{
    features: { laravelPassportSso: LaravelPassportSso; oidcSso: OidcSso[] };
  }>().props;

  const { laravelPassportSso, oidcSso = [] } = features;

  if (!laravelPassportSso && oidcSso.length === 0) {
    return null;
  }

  const rememberParam = `?remember=${remember ? "1" : "0"}`;

  return (
    <div>
      <div className="relative mt-0">
        <div className="absolute inset-0 flex items-center" aria-hidden="true">
          <div className="w-full border-t border-gray-200" />
        </div>
        <div className="relative flex justify-center text-sm leading-6 font-medium">
          <span className="bg-background px-6 text-gray-900 dark:text-white">
            Or continue with
          </span>
        </div>
      </div>

      <div className="mt-6 grid grid-cols-1 gap-4">
        {laravelPassportSso && (
          <SSOButton
            href={`/login/laravelpassport${rememberParam}`}
            label={laravelPassportSso.label}
            logo={laravelPassportSso.logo}
          />
        )}

        {oidcSso.map((connection) => (
          <SSOButton
            key={connection.name}
            href={`/login/oidc/${connection.name}${rememberParam}`}
            label={connection.label}
            logo={connection.logo}
          />
        ))}
      </div>
    </div>
  );
};
