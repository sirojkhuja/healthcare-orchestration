import { useState } from 'react';
import { useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { StateMachineBadge } from '@/components/shared/StateMachineBadge';
import { STALE } from '@/lib/query/queryClient';
import type { TreatmentPlan, TreatmentPlanStatus } from '@/types/api/treatment';
import type { PaginatedResponse } from '@/types/common';

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  approved: 'bg-green-100 text-green-700',
  in_progress: 'bg-blue-100 text-blue-700',
  completed: 'bg-indigo-100 text-indigo-700',
  canceled: 'bg-red-100 text-red-700',
};

const STATUSES: TreatmentPlanStatus[] = ['draft', 'approved', 'in_progress', 'completed', 'canceled'];

export default function TreatmentPlanListPage() {
  const navigate = useNavigate();
  const [statusFilter, setStatusFilter] = useState<TreatmentPlanStatus | 'all'>('all');
  const [page, setPage] = useState(1);

  const { data, isLoading } = useQuery({
    queryKey: ['treatment-plans', 'list', statusFilter, page],
    queryFn: () =>
      api.get<PaginatedResponse<TreatmentPlan>>(endpoints.treatmentPlans, {
        params: { status: statusFilter === 'all' ? undefined : statusFilter, page, per_page: 25 },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<TreatmentPlan>[] = [
    {
      header: 'Title',
      cell: ({ row }) => (
        <span className="font-medium text-blue-600 hover:underline cursor-pointer">{row.original.title}</span>
      ),
    },
    { header: 'Patient', accessorKey: 'patient_name' },
    { header: 'Provider', accessorKey: 'provider_name' },
    {
      header: 'Items',
      cell: ({ row }) => (
        <span className="text-sm text-gray-600">{row.original.items_count}</span>
      ),
    },
    {
      header: 'Status',
      cell: ({ row }) => <StateMachineBadge status={row.original.status} colorMap={STATUS_COLORS} />,
    },
    {
      header: 'Created',
      cell: ({ row }) => format(new Date(row.original.created_at), 'MMM d, yyyy'),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Treatment Plans</h1>

      <div className="flex flex-wrap gap-2">
        <button
          onClick={() => { setStatusFilter('all'); setPage(1); }}
          className={`rounded-full px-3 py-1 text-sm font-medium transition-colors ${statusFilter === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
        >
          All
        </button>
        {STATUSES.map((s) => (
          <button
            key={s}
            onClick={() => { setStatusFilter(s); setPage(1); }}
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
        onRowClick={(row) => navigate(`/treatment-plans/${row.id}`)}
        pagination={data ? { page, pageSize: 25, total: data.meta.total, onPageChange: setPage } : undefined}
        emptyTitle="No treatment plans"
        emptyDescription="Treatment plans will appear here once created."
      />
    </div>
  );
}
