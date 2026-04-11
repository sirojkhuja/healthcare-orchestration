import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { useAuthStore } from '@/store/authStore';
import { useTenantStore } from '@/store/tenantStore';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';
import { ErrorCode } from '@/types/api/errors';
import type { AuthSessionResponse, LoginCredentials } from '@/types/api/auth';

const schema = z.object({
  email: z.string().email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
});
type FormData = z.infer<typeof schema>;

export default function LoginPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { setAuth, setMfaChallenge } = useAuthStore();
  const { setActiveTenant, setTenantList } = useTenantStore();
  const [showPassword, setShowPassword] = useState(false);
  const from = (location.state as { from?: { pathname: string } } | null)?.from?.pathname ?? '/dashboard';

  const { register, handleSubmit, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  const { mutate, isPending, error } = useMutation<AuthSessionResponse, Error, LoginCredentials>({
    mutationFn: (credentials) =>
      api.post<AuthSessionResponse>(endpoints.auth.login, credentials).then((r) => r.data),
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
      if (data.tenant_memberships.length === 1 && data.tenant_memberships[0]) {
        setActiveTenant(data.tenant_memberships[0].tenant_id);
        navigate(from, { replace: true });
      } else {
        navigate('/select-tenant', { replace: true });
      }
    },
    onError: (err: unknown) => {
      const apiErr = err as { code?: string; challenge_id?: string; expires_at?: string };
      if (apiErr?.code === ErrorCode.MFA_REQUIRED && apiErr.challenge_id) {
        setMfaChallenge({ challenge_id: apiErr.challenge_id, expires_at: apiErr.expires_at ?? '' });
        navigate('/mfa');
      }
    },
  });

  return (
    <form onSubmit={handleSubmit((d) => mutate(d))} className="flex flex-col gap-5">
      <h2 className="text-xl font-semibold text-gray-900">Sign in to your account</h2>

      {error && <ApiErrorAlert error={error} />}

      <Input
        label="Email address"
        type="email"
        autoComplete="email"
        autoFocus
        required
        error={errors.email?.message}
        {...register('email')}
      />
      <Input
        label="Password"
        type={showPassword ? 'text' : 'password'}
        autoComplete="current-password"
        required
        error={errors.password?.message}
        rightAddon={
          <button type="button" onClick={() => setShowPassword((v) => !v)} className="text-xs text-gray-500 hover:text-gray-700">
            {showPassword ? 'Hide' : 'Show'}
          </button>
        }
        {...register('password')}
      />

      <div className="flex items-center justify-end">
        <Link to="/forgot-password" className="text-sm text-blue-600 hover:underline">
          Forgot password?
        </Link>
      </div>

      <Button type="submit" isLoading={isPending} className="w-full">
        Sign in
      </Button>
    </form>
  );
}
