import { useState } from 'react';
import { useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { STALE } from '@/lib/query/queryClient';
import type { Encounter } from '@/types/api/treatment';
import type { PaginatedResponse } from '@/types/common';

export default function EncounterListPage() {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['encounters', 'list', page, dateFrom, dateTo],
    queryFn: () =>
      api.get<PaginatedResponse<Encounter>>(endpoints.encounters, {
        params: { page, per_page: 25, date_from: dateFrom || undefined, date_to: dateTo || undefined },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<Encounter>[] = [
    {
      header: 'Date',
      cell: ({ row }) => format(new Date(row.original.occurred_at), 'MMM d, yyyy'),
    },
    {
      header: 'Patient',
      cell: ({ row }) => <span className="font-medium text-gray-900">{row.original.patient_name}</span>,
    },
    { header: 'Provider', accessorKey: 'provider_name' },
    { header: 'Type', accessorKey: 'encounter_type' },
    {
      header: 'Diagnoses',
      cell: ({ row }) => (
        <Badge variant="gray">{row.original.diagnoses_count}</Badge>
      ),
    },
    {
      header: 'Procedures',
      cell: ({ row }) => (
        <Badge variant="gray">{row.original.procedures_count}</Badge>
      ),
    },
    {
      header: 'Status',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'completed' ? 'green' : row.original.status === 'canceled' ? 'red' : 'blue'}>
          {row.original.status}
        </Badge>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Encounters</h1>

      <div className="flex items-center gap-3">
        <Input type="date" label="From" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
        <Input type="date" label="To" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        onRowClick={(row) => navigate(`/encounters/${row.id}`)}
        pagination={data ? { page, pageSize: 25, total: data.meta.total, onPageChange: setPage } : undefined}
        emptyTitle="No encounters found"
        emptyDescription="Encounters will appear after clinical visits are recorded."
      />
    </div>
  );
}
