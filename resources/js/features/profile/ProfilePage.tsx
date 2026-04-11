import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';
import { useAuthStore } from '@/store/authStore';
import { generateIdempotencyKey } from '@/lib/api/idempotency';

const profileSchema = z.object({
  name: z.string().min(1, 'Required'),
  locale: z.string().min(2),
  timezone: z.string().min(1),
});

const passwordSchema = z.object({
  current_password: z.string().min(1, 'Required'),
  password: z.string().min(8, 'At least 8 characters'),
  password_confirmation: z.string().min(1, 'Required'),
}).refine((d) => d.password === d.password_confirmation, {
  message: 'Passwords do not match',
  path: ['password_confirmation'],
});

type ProfileForm = z.infer<typeof profileSchema>;
type PasswordForm = z.infer<typeof passwordSchema>;

export default function ProfilePage() {
  const { user } = useAuthStore();
  const qc = useQueryClient();
  const [mfaSetupOpen, setMfaSetupOpen] = useState(false);
  const [qrCode, setQrCode] = useState<string | null>(null);

  const { register: regProfile, handleSubmit: handleProfile, formState: { errors: profileErrors } } = useForm<ProfileForm>({
    resolver: zodResolver(profileSchema),
    defaultValues: {
      name: user?.name ?? '',
      locale: user?.locale ?? 'en',
      timezone: user?.timezone ?? 'UTC',
    },
  });

  const { register: regPwd, handleSubmit: handlePwd, reset: resetPwd, formState: { errors: pwdErrors } } = useForm<PasswordForm>({
    resolver: zodResolver(passwordSchema),
  });

  const { mutate: saveProfile, isPending: savingProfile, error: profileError } = useMutation({
    mutationFn: (data: ProfileForm) => api.patch('/api/v1/profiles/me', data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['me'] }),
  });

  const { mutate: changePassword, isPending: changingPwd, error: pwdError } = useMutation({
    mutationFn: (data: PasswordForm) => api.post('/api/v1/auth/password/change', data),
    onSuccess: () => resetPwd(),
  });

  const { mutate: setupMfa, isPending: settingUpMfa } = useMutation({
    mutationFn: () =>
      api.post<{ qr_code_url: string; secret: string }>(endpoints.auth.mfa.setup, {}, {
        headers: { 'Idempotency-Key': generateIdempotencyKey() },
      }).then((r) => r.data),
    onSuccess: (data) => {
      setQrCode(data.qr_code_url);
      setMfaSetupOpen(true);
    },
  });

  const { mutate: disableMfa } = useMutation({
    mutationFn: () => api.post(endpoints.auth.mfa.disable, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['me'] }),
  });

  return (
    <div className="flex flex-col gap-8 max-w-2xl">
      <h1 className="text-2xl font-semibold text-gray-900">My Profile</h1>

      {/* Profile info */}
      <form onSubmit={handleProfile((d) => saveProfile(d))} className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm flex flex-col gap-5">
        <h2 className="font-semibold text-gray-900">Personal information</h2>
        {profileError && <ApiErrorAlert error={profileError} />}
        <Input label="Display name" error={profileErrors.name?.message} {...regProfile('name')} />
        <div className="grid grid-cols-2 gap-4">
          <Input label="Locale (e.g. en, uz, ru)" {...regProfile('locale')} />
          <Input label="Timezone (IANA)" placeholder="Asia/Tashkent" {...regProfile('timezone')} />
        </div>
        <div className="flex justify-end">
          <Button type="submit" isLoading={savingProfile}>Save changes</Button>
        </div>
      </form>

      {/* Password change */}
      <form onSubmit={handlePwd((d) => changePassword(d))} className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm flex flex-col gap-5">
        <h2 className="font-semibold text-gray-900">Change password</h2>
        {pwdError && <ApiErrorAlert error={pwdError} />}
        <Input label="Current password" type="password" error={pwdErrors.current_password?.message} {...regPwd('current_password')} />
        <Input label="New password" type="password" error={pwdErrors.password?.message} {...regPwd('password')} />
        <Input label="Confirm new password" type="password" error={pwdErrors.password_confirmation?.message} {...regPwd('password_confirmation')} />
        <div className="flex justify-end">
          <Button type="submit" isLoading={changingPwd}>Change password</Button>
        </div>
      </form>

      {/* MFA */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm flex flex-col gap-4">
        <h2 className="font-semibold text-gray-900">Two-factor authentication</h2>
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-gray-700">Authenticator app (TOTP)</p>
            <Badge variant={user?.mfa_enabled ? 'green' : 'gray'}>
              {user?.mfa_enabled ? 'Enabled' : 'Disabled'}
            </Badge>
          </div>
          {user?.mfa_enabled ? (
            <Button variant="danger" onClick={() => disableMfa()}>Disable MFA</Button>
          ) : (
            <Button onClick={() => setupMfa()} isLoading={settingUpMfa}>Set up authenticator</Button>
          )}
        </div>
      </div>

      {/* MFA setup modal */}
      <Modal isOpen={mfaSetupOpen} onClose={() => setMfaSetupOpen(false)} title="Set up two-factor authentication">
        <div className="flex flex-col gap-4">
          <p className="text-sm text-gray-600">
            Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
          </p>
          {qrCode && (
            <div className="flex justify-center">
              <img src={qrCode} alt="MFA QR code" className="h-48 w-48 rounded-lg border border-gray-200" />
            </div>
          )}
          <p className="text-sm text-gray-500 text-center">
            After scanning, enter the 6-digit code to verify and enable MFA.
          </p>
          <Button className="w-full justify-center" onClick={() => setMfaSetupOpen(false)}>
            Done
          </Button>
        </div>
      </Modal>
    </div>
  );
}
