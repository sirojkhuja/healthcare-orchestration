import { useState } from 'react';
import { useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { ClaimStatusBadge } from '@/components/shared/StateMachineBadge';
import { MoneyDisplay } from '@/components/shared/MoneyDisplay';
import { STALE } from '@/lib/query/queryClient';
import type { Claim, ClaimFilters, ClaimStatus } from '@/types/api/insurance';
import type { PaginatedResponse } from '@/types/common';

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-blue-100 text-blue-700',
  under_review: 'bg-amber-100 text-amber-700',
  approved: 'bg-green-100 text-green-700',
  denied: 'bg-red-100 text-red-700',
  paid: 'bg-indigo-100 text-indigo-700',
};

const STATUSES: ClaimStatus[] = ['draft', 'submitted', 'under_review', 'approved', 'denied', 'paid'];

export default function ClaimListPage() {
  const navigate = useNavigate();
  const [filters, setFilters] = useState<ClaimFilters>({ page: 1, per_page: 25 });
  const [statusFilter, setStatusFilter] = useState<ClaimStatus | 'all'>('all');

  const { data, isLoading } = useQuery({
    queryKey: ['claims', 'list', filters, statusFilter],
    queryFn: () =>
      api.get<PaginatedResponse<Claim>>(endpoints.claims, {
        params: { ...filters, status: statusFilter === 'all' ? undefined : statusFilter },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<Claim>[] = [
    {
      header: 'Claim #',
      cell: ({ row }) => (
        <span className="font-mono text-sm text-gray-700">{row.original.claim_number}</span>
      ),
    },
    {
      header: 'Patient',
      cell: ({ row }) => <span className="font-medium text-gray-900">{row.original.patient_name}</span>,
    },
    { header: 'Payer', accessorKey: 'payer_name' },
    {
      header: 'Service date',
      cell: ({ row }) => format(new Date(row.original.service_date), 'MMM d, yyyy'),
    },
    {
      header: 'Submitted',
      cell: ({ row }) => row.original.submitted_at ? format(new Date(row.original.submitted_at), 'MMM d, yyyy') : '—',
    },
    {
      header: 'Billed',
      cell: ({ row }) => <MoneyDisplay money={row.original.billed_amount} />,
    },
    {
      header: 'Status',
      cell: ({ row }) => <ClaimStatusBadge status={row.original.status} />,
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Insurance Claims</h1>

      <div className="flex flex-wrap gap-2">
        <button
          onClick={() => { setStatusFilter('all'); setFilters((f) => ({ ...f, page: 1 })); }}
          className={`rounded-full px-3 py-1 text-sm font-medium transition-colors ${statusFilter === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
        >
          All
        </button>
        {STATUSES.map((s) => (
          <button
            key={s}
            onClick={() => { setStatusFilter(s); setFilters((f) => ({ ...f, page: 1 })); }}
            className={`rounded-full px-3 py-1 text-sm font-medium capitalize transition-colors ${statusFilter === s ? (STATUS_COLORS[s] ?? '') : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
          >
            {s.replace('_', ' ')}
          </button>
        ))}
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        onRowClick={(row) => navigate(`/insurance/claims/${row.id}`)}
        pagination={data ? {
          page: filters.page ?? 1,
          pageSize: 25,
          total: data.meta.total,
          onPageChange: (page) => setFilters((f) => ({ ...f, page })),
        } : undefined}
        emptyTitle="No claims found"
        emptyDescription="Insurance claims will appear here once created."
      />
    </div>
  );
}
