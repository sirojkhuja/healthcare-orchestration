import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { STALE } from '@/lib/query/queryClient';
import type { PaginatedResponse } from '@/types/common';

interface Prescription {
  id: string;
  patient_id: string;
  patient_name: string;
  provider_name: string;
  medication_name: string;
  dosage: string;
  frequency: string;
  start_date: string;
  end_date?: string;
  status: 'active' | 'completed' | 'canceled';
  notes?: string;
}

export default function PrescriptionListPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'completed' | 'canceled'>('all');

  const { data, isLoading } = useQuery({
    queryKey: ['prescriptions', 'list', page, search, statusFilter],
    queryFn: () =>
      api.get<PaginatedResponse<Prescription>>(endpoints.prescriptions, {
        params: {
          page,
          per_page: 25,
          q: search || undefined,
          status: statusFilter === 'all' ? undefined : statusFilter,
        },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<Prescription>[] = [
    {
      header: 'Patient',
      cell: ({ row }) => <span className="font-medium text-gray-900">{row.original.patient_name}</span>,
    },
    { header: 'Medication', accessorKey: 'medication_name' },
    { header: 'Dosage', accessorKey: 'dosage' },
    { header: 'Frequency', accessorKey: 'frequency' },
    { header: 'Prescriber', accessorKey: 'provider_name' },
    {
      header: 'Start',
      cell: ({ row }) => format(new Date(row.original.start_date), 'MMM d, yyyy'),
    },
    {
      header: 'End',
      cell: ({ row }) => row.original.end_date ? format(new Date(row.original.end_date), 'MMM d, yyyy') : '—',
    },
    {
      header: 'Status',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'active' ? 'green' : row.original.status === 'canceled' ? 'red' : 'gray'} className="capitalize">
          {row.original.status}
        </Badge>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Prescriptions</h1>

      <div className="flex items-center gap-3">
        <div className="flex-1">
          <Input
            placeholder="Search by patient or medication…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <div className="flex gap-2">
          {(['all', 'active', 'completed', 'canceled'] as const).map((s) => (
            <button
              key={s}
              onClick={() => setStatusFilter(s)}
              className={`rounded-full px-3 py-1 text-sm font-medium capitalize transition-colors ${
                statusFilter === s ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {s}
            </button>
          ))}
        </div>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        pagination={data ? { page, pageSize: 25, total: data.meta.total, onPageChange: setPage } : undefined}
        emptyTitle="No prescriptions found"
        emptyDescription="Prescriptions will appear here once created."
      />
    </div>
  );
}
