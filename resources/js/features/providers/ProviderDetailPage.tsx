import { useState } from 'react';
import { useParams, Link } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { format, addDays, startOfWeek } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Spinner } from '@/components/ui/Spinner';
import { AppointmentStatusBadge } from '@/components/shared/StateMachineBadge';
import { STALE } from '@/lib/query/queryClient';
import type { Provider } from '@/types/api/providers';
import type { Appointment, AvailabilitySlot } from '@/types/api/appointments';
import type { PaginatedResponse } from '@/types/common';

const APPT_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  scheduled: 'bg-blue-100 text-blue-700',
  confirmed: 'bg-indigo-100 text-indigo-700',
  checked_in: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-orange-100 text-orange-700',
  completed: 'bg-green-100 text-green-700',
  canceled: 'bg-red-100 text-red-700',
  no_show: 'bg-red-100 text-red-700',
  rescheduled: 'bg-purple-100 text-purple-700',
};

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export default function ProviderDetailPage() {
  const { providerId } = useParams<{ providerId: string }>();
  const [tab, setTab] = useState<'calendar' | 'availability' | 'appointments'>('calendar');
  const [weekStart, setWeekStart] = useState(() => startOfWeek(new Date()));

  const { data: provider, isLoading } = useQuery({
    queryKey: ['providers', 'detail', providerId],
    queryFn: () => api.get<Provider>(endpoints.provider(providerId!)).then((r) => r.data),
    staleTime: STALE.DETAIL,
    enabled: !!providerId,
  });

  const dateFrom = format(weekStart, 'yyyy-MM-dd');
  const dateTo = format(addDays(weekStart, 6), 'yyyy-MM-dd');

  const { data: schedule } = useQuery({
    queryKey: ['providers', 'schedule', providerId, dateFrom],
    queryFn: () =>
      api.get<PaginatedResponse<Appointment>>(endpoints.providerSchedule(providerId!), {
        params: { date_from: dateFrom, date_to: dateTo },
      }).then((r) => r.data),
    staleTime: STALE.REALTIME,
    enabled: !!providerId && tab === 'calendar',
  });

  const { data: slots } = useQuery({
    queryKey: ['providers', 'slots', providerId, dateFrom],
    queryFn: () =>
      api.get<AvailabilitySlot[]>(endpoints.providerAvailabilitySlots(providerId!), {
        params: { date_from: dateFrom, date_to: dateTo, limit: 200 },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: !!providerId && tab === 'availability',
  });

  const { data: appointments } = useQuery({
    queryKey: ['providers', 'appointments', providerId],
    queryFn: () =>
      api.get<PaginatedResponse<Appointment>>(endpoints.appointments, {
        params: { provider_id: providerId, per_page: 25 },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: !!providerId && tab === 'appointments',
  });

  if (isLoading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>;
  if (!provider) return <p className="text-center text-gray-500 py-16">Provider not found.</p>;

  const weekDays = Array.from({ length: 7 }, (_, i) => addDays(weekStart, i));

  return (
    <div className="flex flex-col gap-6">
      {/* Breadcrumb */}
      <nav className="text-sm text-gray-500">
        <Link to="/providers" className="hover:underline">Providers</Link>
        <span className="mx-2">/</span>
        <span className="text-gray-900">{provider.full_name}</span>
      </nav>

      {/* Provider card */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm flex items-start gap-6">
        <div className="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center text-2xl font-bold text-blue-700 shrink-0">
          {provider.first_name[0]}{provider.last_name[0]}
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-3">
            <h1 className="text-xl font-semibold text-gray-900">{provider.full_name}</h1>
            <Badge variant={provider.is_active ? 'green' : 'gray'}>
              {provider.is_active ? 'Active' : 'Inactive'}
            </Badge>
          </div>
          <div className="mt-2 flex flex-wrap gap-2">
            {provider.specialties.map((s) => (
              <Badge key={s.id} variant="blue">{s.name}</Badge>
            ))}
          </div>
          <div className="mt-3 grid grid-cols-2 gap-2 text-sm text-gray-600">
            {provider.clinic_name && <p>Clinic: <span className="font-medium text-gray-900">{provider.clinic_name}</span></p>}
            {provider.department_name && <p>Department: <span className="font-medium text-gray-900">{provider.department_name}</span></p>}
            {provider.email && <p>Email: <span className="font-medium text-gray-900">{provider.email}</span></p>}
            {provider.primary_phone && <p>Phone: <span className="font-medium text-gray-900">{provider.primary_phone.number}</span></p>}
          </div>
        </div>
        <Link to={`/appointments/new?provider_id=${provider.id}`}>
          <Button>Schedule Appointment</Button>
        </Link>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex gap-6">
          {(['calendar', 'availability', 'appointments'] as const).map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`py-3 text-sm font-medium border-b-2 transition-colors capitalize ${
                tab === t
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              {t}
            </button>
          ))}
        </nav>
      </div>

      {/* Calendar tab */}
      {tab === 'calendar' && (
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          <div className="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h2 className="font-semibold text-gray-900">
              Week of {format(weekStart, 'MMM d')}
            </h2>
            <div className="flex gap-2">
              <Button variant="secondary" size="sm" onClick={() => setWeekStart((d) => addDays(d, -7))}>← Prev</Button>
              <Button variant="secondary" size="sm" onClick={() => setWeekStart(startOfWeek(new Date()))}>Today</Button>
              <Button variant="secondary" size="sm" onClick={() => setWeekStart((d) => addDays(d, 7))}>Next →</Button>
            </div>
          </div>
          <div className="grid grid-cols-7 border-b border-gray-100">
            {weekDays.map((day) => (
              <div key={day.toISOString()} className="px-3 py-2 text-center text-xs font-medium text-gray-500 border-r last:border-r-0 border-gray-100">
                <div>{DAYS[day.getDay()]}</div>
                <div className="text-base font-semibold text-gray-900">{format(day, 'd')}</div>
              </div>
            ))}
          </div>
          <div className="grid grid-cols-7 min-h-48">
            {weekDays.map((day) => {
              const dayStr = format(day, 'yyyy-MM-dd');
              const dayAppts = (schedule?.data ?? []).filter(
                (a) => a.scheduled_start_at.startsWith(dayStr)
              );
              return (
                <div key={day.toISOString()} className="border-r last:border-r-0 border-gray-100 p-2 min-h-24">
                  {dayAppts.map((appt) => (
                    <Link
                      key={appt.id}
                      to={`/appointments/${appt.id}`}
                      className="block mb-1 rounded p-1.5 text-xs hover:opacity-90"
                      style={{ backgroundColor: '#EFF6FF' }}
                    >
                      <p className="font-medium text-blue-700 truncate">{appt.patient_name}</p>
                      <p className="text-blue-500">{format(new Date(appt.scheduled_start_at), 'h:mm a')}</p>
                    </Link>
                  ))}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Availability tab */}
      {tab === 'availability' && (
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm p-5">
          <div className="flex items-center justify-between mb-4">
            <h2 className="font-semibold text-gray-900">Available slots</h2>
            <div className="flex gap-2">
              <Button variant="secondary" size="sm" onClick={() => setWeekStart((d) => addDays(d, -7))}>← Prev</Button>
              <Button variant="secondary" size="sm" onClick={() => setWeekStart((d) => addDays(d, 7))}>Next →</Button>
            </div>
          </div>
          {!slots && <Spinner />}
          {slots && slots.length === 0 && (
            <p className="text-sm text-gray-400 text-center py-8">No available slots this week.</p>
          )}
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
            {(slots ?? []).filter((s) => s.is_available).map((slot) => (
              <Link
                key={slot.start_at}
                to={`/appointments/new?provider_id=${provider.id}&start=${slot.start_at}`}
                className="rounded-lg border border-blue-200 bg-blue-50 p-2.5 text-center text-xs font-medium text-blue-700 hover:bg-blue-100 transition-colors"
              >
                <div>{format(new Date(slot.start_at), 'EEE, MMM d')}</div>
                <div className="font-bold">{format(new Date(slot.start_at), 'h:mm a')}</div>
              </Link>
            ))}
          </div>
        </div>
      )}

      {/* Appointments tab */}
      {tab === 'appointments' && (
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          <div className="divide-y divide-gray-100">
            {(appointments?.data ?? []).length === 0 && (
              <p className="px-5 py-8 text-center text-sm text-gray-400">No appointments found.</p>
            )}
            {(appointments?.data ?? []).map((appt) => (
              <Link
                key={appt.id}
                to={`/appointments/${appt.id}`}
                className="flex items-center justify-between px-5 py-3 hover:bg-gray-50"
              >
                <div>
                  <p className="text-sm font-medium text-gray-900">{appt.patient_name}</p>
                  <p className="text-xs text-gray-500">{format(new Date(appt.scheduled_start_at), 'MMM d, yyyy · h:mm a')}</p>
                </div>
                <AppointmentStatusBadge status={appt.status} />
              </Link>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
