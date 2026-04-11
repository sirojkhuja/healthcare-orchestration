import { useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { generateIdempotencyKey } from '@/lib/api/idempotency';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';
import type { Patient, CreatePatientPayload } from '@/types/api/patients';

const schema = z.object({
  first_name: z.string().min(1, 'Required'),
  last_name: z.string().min(1, 'Required'),
  date_of_birth: z.string().min(1, 'Required'),
  gender: z.string().optional(),
  national_id: z.string().optional(),
  email: z.string().email('Invalid email').optional().or(z.literal('')),
  phone_number: z.string().optional(),
});
type FormData = z.infer<typeof schema>;

export default function PatientCreatePage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { register, handleSubmit, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  const { mutate, isPending, error } = useMutation({
    mutationFn: (data: CreatePatientPayload) =>
      api.post<Patient>(endpoints.patients, data, {
        headers: { 'Idempotency-Key': generateIdempotencyKey() },
      }).then((r) => r.data),
    onSuccess: (patient) => {
      void queryClient.invalidateQueries({ queryKey: ['patients', 'list'] });
      navigate(`/patients/${patient.id}`);
    },
  });

  const onSubmit = (data: FormData) => {
    mutate({
      first_name: data.first_name,
      last_name: data.last_name,
      date_of_birth: data.date_of_birth,
      gender: data.gender,
      national_id: data.national_id,
      email: data.email || undefined,
      primary_phone: data.phone_number ? { number: data.phone_number, country: 'UZ' } : undefined,
    });
  };

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center gap-4">
        <button onClick={() => navigate('/patients')} className="text-gray-400 hover:text-gray-600">
          <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h1 className="text-2xl font-semibold text-gray-900">New Patient</h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="max-w-2xl rounded-xl border border-gray-200 bg-white p-6">
        {error && <div className="mb-4"><ApiErrorAlert error={error} /></div>}

        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="First name" required error={errors.first_name?.message} {...register('first_name')} />
          <Input label="Last name" required error={errors.last_name?.message} {...register('last_name')} />
          <Input label="Date of birth" type="date" required error={errors.date_of_birth?.message} {...register('date_of_birth')} />
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Gender</label>
            <select className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" {...register('gender')}>
              <option value="">Select…</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>
          <Input label="National ID" error={errors.national_id?.message} {...register('national_id')} />
          <Input label="Phone number" type="tel" {...register('phone_number')} />
          <div className="sm:col-span-2">
            <Input label="Email address" type="email" error={errors.email?.message} {...register('email')} />
          </div>
        </div>

        <div className="mt-6 flex justify-end gap-3">
          <Button variant="secondary" type="button" onClick={() => navigate('/patients')}>Cancel</Button>
          <Button type="submit" isLoading={isPending}>Save Patient</Button>
        </div>
      </form>
    </div>
  );
}
