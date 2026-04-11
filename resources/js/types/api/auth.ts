import type { UUID, ISO8601 } from '../common';

export interface AuthUser {
  id: UUID;
  email: string;
  name: string;
  avatar_url?: string;
  locale: string;
  timezone: string;
  mfa_enabled: boolean;
}

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
}

export interface AuthSession {
  id: UUID;
  created_at: ISO8601;
  last_active_at: ISO8601;
  user_agent?: string;
  ip_address?: string;
  is_current: boolean;
}

export interface AuthSessionResponse {
  user: AuthUser;
  session: { id: UUID };
  tokens: AuthTokens;
  permissions: string[];
  tenant_memberships: TenantMembership[];
}

export interface TenantMembership {
  tenant_id: UUID;
  tenant_name: string;
  tenant_logo_url?: string;
  role: string;
  is_active: boolean;
}

export interface MfaChallengeResponse {
  challenge_id: UUID;
  expires_at: ISO8601;
}

export interface MfaSetupResponse {
  qr_code_url: string;
  secret: string;
  recovery_codes: string[];
}

export interface ApiKey {
  id: UUID;
  name: string;
  key_prefix: string;
  last_used_at?: ISO8601;
  expires_at?: ISO8601;
  scopes: string[];
  created_at: ISO8601;
}

export interface ApiKeyCreatedResponse extends ApiKey {
  plaintext_key: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface MfaVerifyPayload {
  challenge_id: UUID;
  code: string;
}

export interface RefreshTokenPayload {
  refresh_token: string;
}
