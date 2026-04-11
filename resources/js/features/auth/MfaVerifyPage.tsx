import { useState, useEffect, useRef } from 'react';
import { Link, useNavigate } from 'react-router';
import { useMutation } from '@tanstack/react-query';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { useAuthStore } from '@/store/authStore';
import { useTenantStore } from '@/store/tenantStore';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';
import type { AuthSessionResponse, MfaVerifyPayload } from '@/types/api/auth';

export default function MfaVerifyPage() {
  const navigate = useNavigate();
  const { mfaChallenge, setAuth, clearMfaChallenge } = useAuthStore();
  const { setActiveTenant, setTenantList } = useTenantStore();
  const [code, setCode] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!mfaChallenge) navigate('/login', { replace: true });
  }, [mfaChallenge, navigate]);

  const { mutate, isPending, error } = useMutation({
    mutationFn: (payload: MfaVerifyPayload) =>
      api.post<AuthSessionResponse>(endpoints.auth.mfa.verify, payload).then((r) => r.data),
    onSuccess: (data) => {
      setAuth({
        accessToken: data.tokens.access_token,
        refreshToken: data.tokens.refresh_token,
        user: data.user,
        sessionId: data.session.id,
        permissions: data.permissions,
        tenantMemberships: data.tenant_memberships,
      });
      setTenantList(data.tenant_memberships);
      clearMfaChallenge();
      if (data.tenant_memberships.length === 1 && data.tenant_memberships[0]) {
        setActiveTenant(data.tenant_memberships[0].tenant_id);
        navigate('/dashboard', { replace: true });
      } else {
        navigate('/select-tenant', { replace: true });
      }
    },
  });

  const handleCodeChange = (value: string) => {
    setCode(value);
    if (!useRecovery && value.replace(/\s/g, '').length === 6) {
      mutate({ challenge_id: mfaChallenge!.challenge_id, code: value.replace(/\s/g, '') });
    }
  };

  if (!mfaChallenge) return null;

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h2 className="text-xl font-semibold text-gray-900">Two-factor authentication</h2>
        <p className="mt-1 text-sm text-gray-500">
          {useRecovery
            ? 'Enter one of your recovery codes'
            : 'Enter the 6-digit code from your authenticator app'}
        </p>
      </div>

      {error && <ApiErrorAlert error={error} />}

      <Input
        ref={inputRef}
        label={useRecovery ? 'Recovery code' : 'Authentication code'}
        value={code}
        onChange={(e) => handleCodeChange(e.target.value)}
        autoFocus
        maxLength={useRecovery ? 10 : 6}
        autoComplete="one-time-code"
        inputMode={useRecovery ? 'text' : 'numeric'}
        className="text-center tracking-widest text-lg"
      />

      {!useRecovery && (
        <Button
          type="button"
          onClick={() => mutate({ challenge_id: mfaChallenge.challenge_id, code })}
          isLoading={isPending}
          disabled={code.length < 6}
          className="w-full"
        >
          Verify
        </Button>
      )}

      {useRecovery && (
        <Button
          type="button"
          onClick={() => mutate({ challenge_id: mfaChallenge.challenge_id, code })}
          isLoading={isPending}
          className="w-full"
        >
          Use recovery code
        </Button>
      )}

      <button
        type="button"
        onClick={() => { setUseRecovery((v) => !v); setCode(''); }}
        className="text-sm text-blue-600 hover:underline text-center"
      >
        {useRecovery ? 'Use authenticator code instead' : 'Use a recovery code instead'}
      </button>

      <Link to="/login" onClick={clearMfaChallenge} className="text-center text-sm text-gray-500 hover:text-gray-700">
        Back to sign in
      </Link>
    </div>
  );
}
