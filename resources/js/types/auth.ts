export type User = {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  avatar?: string;
  email_verified_at: string | null;
  role: "user" | "admin" | "owner";
  last_login_at: string | null;
  two_factor_enabled?: boolean;
  has_password?: boolean;
  created_at: string;
  updated_at: string;
  [key: string]: unknown;
};

export type Auth = {
  user: User;
};

export type SharedProps = {
  auth: Auth;
  can: {
    administrate: boolean;
  };
  flash: {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
  };
  name: string;
  sidebarOpen: boolean;
};

export type TwoFactorSetupData = {
  svg: string;
  url: string;
};

export type TwoFactorSecretKey = {
  secretKey: string;
};
