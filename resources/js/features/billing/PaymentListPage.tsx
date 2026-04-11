import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { StateMachineBadge } from '@/components/shared/StateMachineBadge';
import { MoneyDisplay } from '@/components/shared/MoneyDisplay';
import { STALE } from '@/lib/query/queryClient';
import type { Payment, PaymentFilters, PaymentStatus } from '@/types/api/billing';
import type { PaginatedResponse } from '@/types/common';

const STATUS_COLORS: Record<string, string> = {
  initiated: 'bg-gray-100 text-gray-700',
  pending: 'bg-blue-100 text-blue-700',
  captured: 'bg-green-100 text-green-700',
  failed: 'bg-red-100 text-red-700',
  canceled: 'bg-red-100 text-red-700',
  refunded: 'bg-purple-100 text-purple-700',
};

const STATUSES: PaymentStatus[] = ['initiated', 'pending', 'captured', 'failed', 'canceled', 'refunded'];

export default function PaymentListPage() {
  const [filters, setFilters] = useState<PaymentFilters>({ page: 1, per_page: 25 });
  const [statusFilter, setStatusFilter] = useState<PaymentStatus | 'all'>('all');

  const { data, isLoading } = useQuery({
    queryKey: ['payments', 'list', filters, statusFilter],
    queryFn: () =>
      api.get<PaginatedResponse<Payment>>(endpoints.payments, {
        params: { ...filters, status: statusFilter === 'all' ? undefined : statusFilter },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<Payment>[] = [
    {
      header: 'ID',
      cell: ({ row }) => (
        <code className="text-xs font-mono text-gray-500">{row.original.id.slice(0, 8)}…</code>
      ),
    },
    {
      header: 'Patient',
      cell: ({ row }) => <span className="font-medium text-gray-900">{row.original.patient_name}</span>,
    },
    {
      header: 'Invoice',
      cell: ({ row }) => (
        <span className="font-mono text-sm text-blue-600">#{row.original.invoice_number}</span>
      ),
    },
    {
      header: 'Provider',
      cell: ({ row }) => (
        <span className="capitalize text-gray-700">{row.original.provider.replace('_', ' ')}</span>
      ),
    },
    {
      header: 'Amount',
      cell: ({ row }) => <MoneyDisplay money={row.original.amount} />,
    },
    {
      header: 'Date',
      cell: ({ row }) => format(new Date(row.original.initiated_at), 'MMM d, yyyy'),
    },
    {
      header: 'Status',
      cell: ({ row }) => <StateMachineBadge status={row.original.status} colorMap={STATUS_COLORS} />,
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Payments</h1>

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
            {s}
          </button>
        ))}
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        pagination={data ? {
          page: filters.page ?? 1,
          pageSize: 25,
          total: data.meta.total,
          onPageChange: (page) => setFilters((f) => ({ ...f, page })),
        } : undefined}
        emptyTitle="No payments found"
        emptyDescription="Payments will appear here once initiated."
      />
    </div>
  );
}
