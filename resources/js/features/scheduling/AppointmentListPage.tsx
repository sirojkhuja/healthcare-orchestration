import { useState } from 'react';
import { useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { StateMachineBadge } from '@/components/shared/StateMachineBadge';
import { STALE } from '@/lib/query/queryClient';
import type { Appointment, AppointmentFilters, AppointmentStatus } from '@/types/api/appointments';
import type { PaginatedResponse } from '@/types/common';

const STATUS_COLORS: Record<string, string> = {
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

const ALL_STATUSES: AppointmentStatus[] = [
  'draft', 'scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'canceled', 'no_show', 'rescheduled',
];

export default function AppointmentListPage() {
  const navigate = useNavigate();
  const [filters, setFilters] = useState<AppointmentFilters>({ page: 1, per_page: 25 });
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<AppointmentStatus | 'all'>('all');

  const { data, isLoading } = useQuery({
    queryKey: ['appointments', 'list', filters],
    queryFn: () =>
      api.get<PaginatedResponse<Appointment>>(endpoints.appointments, { params: filters }).then((r) => r.data),
    staleTime: STALE.REALTIME,
  });

  const applyStatus = (s: AppointmentStatus | 'all') => {
    setStatusFilter(s);
    setFilters((f) => ({ ...f, status: s === 'all' ? undefined : s, page: 1 }));
  };

  const columns: ColumnDef<Appointment>[] = [
    {
      header: 'Date & Time',
      cell: ({ row }) => (
        <div>
          <p className="text-sm font-medium text-gray-900">
            {format(new Date(row.original.scheduled_start_at), 'MMM d, yyyy')}
          </p>
          <p className="text-xs text-gray-500">
            {format(new Date(row.original.scheduled_start_at), 'h:mm a')} · {row.original.duration_minutes}min
          </p>
        </div>
      ),
    },
    {
      header: 'Patient',
      cell: ({ row }) => (
        <span className="text-sm font-medium text-blue-600 hover:underline cursor-pointer">
          {row.original.patient_name}
        </span>
      ),
    },
    { header: 'Provider', accessorKey: 'provider_name' },
    { header: 'Clinic', accessorKey: 'clinic_name' },
    {
      header: 'Type',
      cell: ({ row }) => (
        <span className="capitalize text-sm text-gray-600">
          {row.original.appointment_type.replace('_', ' ')}
        </span>
      ),
    },
    {
      header: 'Status',
      cell: ({ row }) => <StateMachineBadge status={row.original.status} colorMap={STATUS_COLORS} />,
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Appointments</h1>
        <div className="flex gap-2">
          <Button
            variant="secondary"
            onClick={() => window.open(`${endpoints.appointmentExport}`, '_blank')}
          >
            Export CSV
          </Button>
          <Button onClick={() => navigate('/appointments/new')}>+ New Appointment</Button>
        </div>
      </div>

      {/* Status filter chips */}
      <div className="flex flex-wrap gap-2">
        <button
          onClick={() => applyStatus('all')}
          className={`rounded-full px-3 py-1 text-sm font-medium transition-colors ${
            statusFilter === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
          }`}
        >
          All
        </button>
        {ALL_STATUSES.map((s) => (
          <button
            key={s}
            onClick={() => applyStatus(s)}
            className={`rounded-full px-3 py-1 text-sm font-medium capitalize transition-colors ${
              statusFilter === s
                ? (STATUS_COLORS[s] ?? 'bg-gray-100 text-gray-700')
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
          >
            {s.replace('_', ' ')}
          </button>
        ))}
      </div>

      {/* Search */}
      <div className="flex items-center gap-3">
        <div className="flex-1">
          <Input
            placeholder="Search by patient name…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') setFilters((f) => ({ ...f, q: search || undefined, page: 1 }));
            }}
          />
        </div>
        <Button variant="secondary" onClick={() => setFilters((f) => ({ ...f, q: search || undefined, page: 1 }))}>
          Search
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        onRowClick={(row) => navigate(`/appointments/${row.id}`)}
        pagination={data ? {
          page: filters.page ?? 1,
          pageSize: filters.per_page ?? 25,
          total: data.meta.total,
          onPageChange: (page) => setFilters((f) => ({ ...f, page })),
        } : undefined}
        emptyTitle="No appointments found"
        emptyDescription="Try changing the status filter or search terms."
      />
    </div>
  );
}
