import { useState } from 'react';
import { useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/Badge';
import { LabOrderStatusBadge } from '@/components/shared/StateMachineBadge';
import { STALE } from '@/lib/query/queryClient';
import type { LabOrder, LabOrderStatus, LabOrderFilters } from '@/types/api/lab';
import type { PaginatedResponse } from '@/types/common';

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-blue-100 text-blue-700',
  in_progress: 'bg-amber-100 text-amber-700',
  completed: 'bg-green-100 text-green-700',
  canceled: 'bg-red-100 text-red-700',
};

const STATUSES: LabOrderStatus[] = ['draft', 'submitted', 'in_progress', 'completed', 'canceled'];

export default function LabOrderListPage() {
  const navigate = useNavigate();
  const [statusFilter, setStatusFilter] = useState<LabOrderStatus | 'all'>('all');
  const [filters, setFilters] = useState<LabOrderFilters>({ page: 1, per_page: 25 });

  const { data, isLoading } = useQuery({
    queryKey: ['lab-orders', 'list', statusFilter, filters],
    queryFn: () =>
      api.get<PaginatedResponse<LabOrder>>(endpoints.labOrders, {
        params: { ...filters, status: statusFilter === 'all' ? undefined : statusFilter },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<LabOrder>[] = [
    {
      header: 'Order ID',
      cell: ({ row }) => (
        <code className="text-xs font-mono text-gray-600">{row.original.id.slice(0, 8)}…</code>
      ),
    },
    {
      header: 'Patient',
      cell: ({ row }) => <span className="font-medium text-gray-900">{row.original.patient_name}</span>,
    },
    { header: 'Provider', accessorKey: 'provider_name' },
    {
      header: 'Ordered',
      cell: ({ row }) => format(new Date(row.original.ordered_at), 'MMM d, yyyy'),
    },
    {
      header: 'Tests',
      cell: ({ row }) => <Badge variant="gray">{row.original.tests_count}</Badge>,
    },
    {
      header: 'Results',
      cell: ({ row }) => (
        <Badge variant={row.original.results_received ? 'green' : 'gray'}>
          {row.original.results_received ? 'Received' : 'Pending'}
        </Badge>
      ),
    },
    {
      header: 'Status',
      cell: ({ row }) => <LabOrderStatusBadge status={row.original.status} />,
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Lab Orders</h1>

      <div className="flex flex-wrap gap-2">
        <button
          onClick={() => setStatusFilter('all')}
          className={`rounded-full px-3 py-1 text-sm font-medium transition-colors ${statusFilter === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
        >
          All
        </button>
        {STATUSES.map((s) => (
          <button
            key={s}
            onClick={() => setStatusFilter(s)}
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
        onRowClick={(row) => navigate(`/lab-orders/${row.id}`)}
        pagination={data ? {
          page: filters.page ?? 1,
          pageSize: 25,
          total: data.meta.total,
          onPageChange: (page) => setFilters((f) => ({ ...f, page })),
        } : undefined}
        emptyTitle="No lab orders found"
        emptyDescription="Lab orders will appear here once created."
      />
    </div>
  );
}
