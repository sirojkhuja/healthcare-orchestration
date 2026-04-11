import { useState } from 'react';
import { useNavigate, Link, useSearchParams } from 'react-router';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery } from '@tanstack/react-query';
import { format, addDays } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';
import { generateIdempotencyKey } from '@/lib/api/idempotency';
import { STALE } from '@/lib/query/queryClient';
import type { AvailabilitySlot, CreateAppointmentPayload } from '@/types/api/appointments';
import type { Patient } from '@/types/api/patients';
import type { Provider } from '@/types/api/providers';
import type { PaginatedResponse } from '@/types/common';

const schema = z.object({
  patient_id: z.string().uuid('Select a patient'),
  provider_id: z.string().uuid('Select a provider'),
  appointment_type: z.enum(['in_person', 'telehealth', 'phone']),
  service_reason: z.string().optional(),
  notes: z.string().optional(),
  notify_patient: z.boolean().default(true),
});
type FormData = z.infer<typeof schema>;

type Step = 1 | 2 | 3;

export default function AppointmentCreatePage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [step, setStep] = useState<Step>(1);
  const [selectedSlot, setSelectedSlot] = useState<AvailabilitySlot | null>(null);
  const [patientSearch, setPatientSearch] = useState('');
  const [providerSearch, setProviderSearch] = useState('');
  const [slotDate, setSlotDate] = useState(() => format(addDays(new Date(), 1), 'yyyy-MM-dd'));

  const { control, register, handleSubmit, watch, setValue, formState: { errors } } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      patient_id: '',
      provider_id: searchParams.get('provider_id') ?? '',
      appointment_type: 'in_person',
      notify_patient: true,
    },
  });

  const watchedProviderId = watch('provider_id');

  // Patient search
  const { data: patients, isFetching: patientsFetching } = useQuery({
    queryKey: ['patients', 'search', patientSearch],
    queryFn: () =>
      api.get<PaginatedResponse<Patient>>(endpoints.patients, { params: { q: patientSearch, per_page: 8 } })
        .then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: patientSearch.length >= 2,
  });

  // Provider search
  const { data: providers, isFetching: providersFetching } = useQuery({
    queryKey: ['providers', 'search', providerSearch],
    queryFn: () =>
      api.get<PaginatedResponse<Provider>>(endpoints.providers, { params: { q: providerSearch, per_page: 8 } })
        .then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: providerSearch.length >= 2,
  });

  // Availability slots
  const { data: slots, isLoading: slotsLoading } = useQuery({
    queryKey: ['providers', 'slots', watchedProviderId, slotDate],
    queryFn: () =>
      api.get<AvailabilitySlot[]>(endpoints.providerAvailabilitySlots(watchedProviderId), {
        params: { date_from: slotDate, date_to: format(addDays(new Date(slotDate), 6), 'yyyy-MM-dd'), limit: 200 },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: !!watchedProviderId && step === 2,
  });

  const { mutate, isPending, error } = useMutation({
    mutationFn: (payload: CreateAppointmentPayload) =>
      api.post<{ id: string }>(endpoints.appointments, payload, {
        headers: { 'Idempotency-Key': generateIdempotencyKey() },
      }).then((r) => r.data),
    onSuccess: (data) => navigate(`/appointments/${data.id}`),
  });

  const onSubmit = (form: FormData) => {
    if (!selectedSlot) return;
    mutate({
      patient_id: form.patient_id,
      provider_id: form.provider_id,
      clinic_id: '', // ideally from provider data
      scheduled_start_at: selectedSlot.start_at,
      scheduled_end_at: selectedSlot.end_at,
      timezone: selectedSlot.timezone,
      appointment_type: form.appointment_type,
      service_reason: form.service_reason,
      notes: form.notes,
      notify_patient: form.notify_patient,
    });
  };

  const selectedPatient = patients?.data.find((p) => p.id === watch('patient_id'));
  const selectedProvider = providers?.data.find((p) => p.id === watchedProviderId);

  return (
    <div className="flex flex-col gap-6 max-w-2xl mx-auto">
      <nav className="text-sm text-gray-500">
        <Link to="/appointments" className="hover:underline">Appointments</Link>
        <span className="mx-2">/</span>
        <span className="text-gray-900">New appointment</span>
      </nav>

      <h1 className="text-2xl font-semibold text-gray-900">Schedule Appointment</h1>

      {/* Step indicators */}
      <div className="flex items-center gap-3">
        {([1, 2, 3] as const).map((s) => (
          <div key={s} className="flex items-center gap-2">
            <div className={`h-7 w-7 rounded-full flex items-center justify-center text-sm font-semibold ${
              s < step ? 'bg-green-500 text-white' : s === step ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'
            }`}>
              {s < step ? '✓' : s}
            </div>
            <span className={`text-sm ${s === step ? 'font-medium text-gray-900' : 'text-gray-400'}`}>
              {s === 1 ? 'Select' : s === 2 ? 'Pick slot' : 'Review'}
            </span>
            {s < 3 && <span className="text-gray-300 ml-1">—</span>}
          </div>
        ))}
      </div>

      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        {error && <ApiErrorAlert error={error} />}

        {/* Step 1 */}
        {step === 1 && (
          <div className="flex flex-col gap-5">
            <h2 className="font-semibold text-gray-900">Select patient & provider</h2>

            {/* Patient search */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Patient</label>
              {selectedPatient ? (
                <div className="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                  <span className="text-sm font-medium text-blue-700">{selectedPatient.full_name}</span>
                  <button
                    type="button"
                    onClick={() => { setValue('patient_id', ''); setPatientSearch(''); }}
                    className="text-xs text-blue-500 hover:text-blue-700"
                  >
                    Change
                  </button>
                </div>
              ) : (
                <div>
                  <Input
                    placeholder="Search by name or ID…"
                    value={patientSearch}
                    onChange={(e) => setPatientSearch(e.target.value)}
                  />
                  {patientsFetching && <Spinner size="sm" className="mt-2" />}
                  {(patients?.data ?? []).length > 0 && !selectedPatient && (
                    <ul className="mt-1 rounded-lg border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                      {patients!.data.map((p) => (
                        <li key={p.id}>
                          <button
                            type="button"
                            onClick={() => { setValue('patient_id', p.id); setPatientSearch(''); }}
                            className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50"
                          >
                            <span className="font-medium">{p.full_name}</span>
                            <span className="text-gray-400 ml-2">{p.date_of_birth}</span>
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
              {errors.patient_id && <p className="mt-1 text-xs text-red-600">{errors.patient_id.message}</p>}
            </div>

            {/* Provider search */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Provider</label>
              {selectedProvider ? (
                <div className="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                  <span className="text-sm font-medium text-blue-700">{selectedProvider.full_name}</span>
                  <button
                    type="button"
                    onClick={() => { setValue('provider_id', ''); setProviderSearch(''); }}
                    className="text-xs text-blue-500 hover:text-blue-700"
                  >
                    Change
                  </button>
                </div>
              ) : (
                <div>
                  <Input
                    placeholder="Search by name or specialty…"
                    value={providerSearch}
                    onChange={(e) => setProviderSearch(e.target.value)}
                  />
                  {providersFetching && <Spinner size="sm" className="mt-2" />}
                  {(providers?.data ?? []).length > 0 && (
                    <ul className="mt-1 rounded-lg border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                      {providers!.data.map((p) => (
                        <li key={p.id}>
                          <button
                            type="button"
                            onClick={() => { setValue('provider_id', p.id); setProviderSearch(''); }}
                            className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50"
                          >
                            <span className="font-medium">{p.full_name}</span>
                            {p.specialties[0] && (
                              <span className="text-gray-400 ml-2">{p.specialties[0].name}</span>
                            )}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
            </div>

            {/* Appointment type */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Appointment type</label>
              <div className="flex gap-3">
                {(['in_person', 'telehealth', 'phone'] as const).map((type) => (
                  <Controller
                    key={type}
                    control={control}
                    name="appointment_type"
                    render={({ field }) => (
                      <button
                        type="button"
                        onClick={() => field.onChange(type)}
                        className={`flex-1 rounded-lg border py-2 text-sm font-medium capitalize transition-colors ${
                          field.value === type
                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                        }`}
                      >
                        {type.replace('_', ' ')}
                      </button>
                    )}
                  />
                ))}
              </div>
            </div>

            <Input label="Service / reason (optional)" {...register('service_reason')} />

            <div className="flex justify-end">
              <Button onClick={() => setStep(2)} disabled={!watch('patient_id') || !watch('provider_id')}>
                Next: Pick a slot →
              </Button>
            </div>
          </div>
        )}

        {/* Step 2 */}
        {step === 2 && (
          <div className="flex flex-col gap-5">
            <h2 className="font-semibold text-gray-900">Choose a time slot</h2>

            <Input
              label="Week starting"
              type="date"
              value={slotDate}
              onChange={(e) => { setSlotDate(e.target.value); setSelectedSlot(null); }}
            />

            {slotsLoading && <div className="flex justify-center py-6"><Spinner /></div>}

            {!slotsLoading && (slots ?? []).filter((s) => s.is_available).length === 0 && (
              <p className="text-center text-sm text-gray-400 py-4">No available slots. Try another week.</p>
            )}

            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
              {(slots ?? []).filter((s) => s.is_available).map((slot) => (
                <button
                  key={slot.start_at}
                  type="button"
                  onClick={() => setSelectedSlot(slot)}
                  className={`rounded-lg border p-2 text-xs font-medium transition-colors ${
                    selectedSlot?.start_at === slot.start_at
                      ? 'border-blue-500 bg-blue-600 text-white'
                      : 'border-gray-200 bg-white text-gray-700 hover:border-blue-300 hover:bg-blue-50'
                  }`}
                >
                  <div>{format(new Date(slot.start_at), 'EEE, MMM d')}</div>
                  <div className="font-bold">{format(new Date(slot.start_at), 'h:mm a')}</div>
                </button>
              ))}
            </div>

            <div className="flex justify-between">
              <Button variant="secondary" onClick={() => setStep(1)}>← Back</Button>
              <Button onClick={() => setStep(3)} disabled={!selectedSlot}>
                Next: Review →
              </Button>
            </div>
          </div>
        )}

        {/* Step 3 */}
        {step === 3 && (
          <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-5">
            <h2 className="font-semibold text-gray-900">Review & confirm</h2>

            <dl className="grid grid-cols-2 gap-3 text-sm rounded-lg bg-gray-50 p-4">
              <div>
                <dt className="text-gray-500">Patient</dt>
                <dd className="font-medium text-gray-900">{selectedPatient?.full_name}</dd>
              </div>
              <div>
                <dt className="text-gray-500">Provider</dt>
                <dd className="font-medium text-gray-900">{selectedProvider?.full_name}</dd>
              </div>
              <div>
                <dt className="text-gray-500">Date & time</dt>
                <dd className="font-medium text-gray-900">
                  {selectedSlot && format(new Date(selectedSlot.start_at), 'EEE, MMM d · h:mm a')}
                  <span className="text-gray-400 ml-1">({selectedSlot?.timezone})</span>
                </dd>
              </div>
              <div>
                <dt className="text-gray-500">Type</dt>
                <dd className="font-medium text-gray-900 capitalize">{watch('appointment_type').replace('_', ' ')}</dd>
              </div>
              {watch('service_reason') && (
                <div className="col-span-2">
                  <dt className="text-gray-500">Reason</dt>
                  <dd className="font-medium text-gray-900">{watch('service_reason')}</dd>
                </div>
              )}
            </dl>

            <Input label="Notes (optional)" {...register('notes')} />

            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" {...register('notify_patient')} className="rounded" />
              Send confirmation to patient
            </label>

            <div className="flex justify-between">
              <Button variant="secondary" onClick={() => setStep(2)}>← Back</Button>
              <Button type="submit" isLoading={isPending}>Schedule Appointment</Button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
