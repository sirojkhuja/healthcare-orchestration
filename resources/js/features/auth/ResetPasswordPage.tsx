import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';

const schema = z.object({
  password: z.string().min(8, 'Password must be at least 8 characters'),
  password_confirmation: z.string(),
}).refine((d) => d.password === d.password_confirmation, {
  message: 'Passwords do not match',
  path: ['password_confirmation'],
});
type FormData = z.infer<typeof schema>;

export default function ResetPasswordPage() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const email = params.get('email') ?? '';

  const { register, handleSubmit, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  const { mutate, isPending, error, isSuccess } = useMutation({
    mutationFn: (data: FormData) =>
      api.post(endpoints.auth.password.reset, { ...data, token, email }),
    onSuccess: () => setTimeout(() => navigate('/login'), 2000),
  });

  if (isSuccess) {
    return (
      <div className="flex flex-col items-center gap-4 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
          <svg className="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 className="text-lg font-semibold text-gray-900">Password reset!</h2>
        <p className="text-sm text-gray-500">Redirecting to sign in…</p>
        <Link to="/login" className="text-sm text-blue-600 hover:underline">Sign in now</Link>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit((d) => mutate(d))} className="flex flex-col gap-5">
      <h2 className="text-xl font-semibold text-gray-900">Set new password</h2>
      {error && <ApiErrorAlert error={error} />}
      <Input
        label="New password"
        type="password"
        autoComplete="new-password"
        autoFocus
        required
        error={errors.password?.message}
        {...register('password')}
      />
      <Input
        label="Confirm password"
        type="password"
        autoComplete="new-password"
        required
        error={errors.password_confirmation?.message}
        {...register('password_confirmation')}
      />
      <Button type="submit" isLoading={isPending} className="w-full">
        Reset password
      </Button>
    </form>
  );
}
