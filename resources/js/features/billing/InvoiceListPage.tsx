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
import { MoneyDisplay } from '@/components/shared/MoneyDisplay';
import { STALE } from '@/lib/query/queryClient';
import type { Invoice, InvoiceFilters, InvoiceStatus } from '@/types/api/billing';
import type { PaginatedResponse } from '@/types/common';

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  issued: 'bg-blue-100 text-blue-700',
  partially_paid: 'bg-amber-100 text-amber-700',
  paid: 'bg-green-100 text-green-700',
  canceled: 'bg-red-100 text-red-700',
};

const STATUSES: InvoiceStatus[] = ['draft', 'issued', 'partially_paid', 'paid', 'canceled'];

export default function InvoiceListPage() {
  const navigate = useNavigate();
  const [filters, setFilters] = useState<InvoiceFilters>({ page: 1, per_page: 25 });
  const [statusFilter, setStatusFilter] = useState<InvoiceStatus | 'all'>('all');
  const [search, setSearch] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', 'list', filters, statusFilter],
    queryFn: () =>
      api.get<PaginatedResponse<Invoice>>(endpoints.invoices, {
        params: { ...filters, status: statusFilter === 'all' ? undefined : statusFilter },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<Invoice>[] = [
    {
      header: 'Invoice #',
      cell: ({ row }) => (
        <span className="font-mono text-sm text-gray-700">{row.original.invoice_number}</span>
      ),
    },
    {
      header: 'Patient',
      cell: ({ row }) => <span className="font-medium text-gray-900">{row.original.patient_name}</span>,
    },
    {
      header: 'Issued',
      cell: ({ row }) => row.original.issued_at ? format(new Date(row.original.issued_at), 'MMM d, yyyy') : '—',
    },
    {
      header: 'Due',
      cell: ({ row }) => row.original.due_date ? format(new Date(row.original.due_date), 'MMM d, yyyy') : '—',
    },
    {
      header: 'Total',
      cell: ({ row }) => <MoneyDisplay money={row.original.total} />,
    },
    {
      header: 'Balance',
      cell: ({ row }) => (
        <span className={row.original.balance_due.amount > 0 ? 'font-semibold text-red-600' : 'text-green-600'}>
          <MoneyDisplay money={row.original.balance_due} />
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
        <h1 className="text-2xl font-semibold text-gray-900">Invoices</h1>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => window.open(endpoints.invoices + '/export', '_blank')}>
            Export CSV
          </Button>
          <Button onClick={() => navigate('/billing/invoices/new')}>+ New Invoice</Button>
        </div>
      </div>

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

      <div className="flex items-center gap-3">
        <div className="flex-1">
          <Input
            placeholder="Search by patient name…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && setFilters((f) => ({ ...f, q: search || undefined, page: 1 }))}
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
        onRowClick={(row) => navigate(`/billing/invoices/${row.id}`)}
        pagination={data ? {
          page: filters.page ?? 1,
          pageSize: 25,
          total: data.meta.total,
          onPageChange: (page) => setFilters((f) => ({ ...f, page })),
        } : undefined}
        emptyTitle="No invoices found"
        emptyDescription="Invoices will appear here once created."
      />
    </div>
  );
}
