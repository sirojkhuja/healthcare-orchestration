import { create } from 'zustand';
import { tokenStorage } from '@/lib/auth/tokens';
import type { AuthUser, TenantMembership, MfaChallengeResponse } from '@/types/api/auth';

interface AuthState {
  accessToken: string | null;
  user: AuthUser | null;
  sessionId: string | null;
  permissions: string[];
  tenantMemberships: TenantMembership[];
  mfaChallenge: MfaChallengeResponse | null;
  isAuthenticated: boolean;
}

interface AuthActions {
  setAuth: (params: {
    accessToken: string;
    refreshToken: string;
    user: AuthUser;
    sessionId: string;
    permissions: string[];
    tenantMemberships: TenantMembership[];
  }) => void;
  setAccessToken: (token: string) => void;
  setMfaChallenge: (challenge: MfaChallengeResponse) => void;
  clearMfaChallenge: () => void;
  clearAuth: () => void;
  hasPermission: (permission: string) => boolean;
}

export const useAuthStore = create<AuthState & AuthActions>((set, get) => ({
  accessToken: null,
  user: null,
  sessionId: null,
  permissions: [],
  tenantMemberships: [],
  mfaChallenge: null,
  isAuthenticated: false,

  setAuth: ({ accessToken, refreshToken, user, sessionId, permissions, tenantMemberships }) => {
    tokenStorage.setRefreshToken(refreshToken);
    set({
      accessToken,
      user,
      sessionId,
      permissions,
      tenantMemberships,
      isAuthenticated: true,
      mfaChallenge: null,
    });
  },

  setAccessToken: (token) => set({ accessToken: token }),

  setMfaChallenge: (challenge) => set({ mfaChallenge: challenge }),

  clearMfaChallenge: () => set({ mfaChallenge: null }),

  clearAuth: () => {
    tokenStorage.clearRefreshToken();
    set({
      accessToken: null,
      user: null,
      sessionId: null,
      permissions: [],
      tenantMemberships: [],
      mfaChallenge: null,
      isAuthenticated: false,
    });
  },

  hasPermission: (permission: string) => get().permissions.includes(permission),
}));
