import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/Badge';
import { MoneyDisplay } from '@/components/shared/MoneyDisplay';
import { STALE } from '@/lib/query/queryClient';
import type { BillableService } from '@/types/api/billing';
import type { PaginatedResponse } from '@/types/common';

export default function PriceListPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['billable-services', 'list'],
    queryFn: () =>
      api.get<PaginatedResponse<BillableService>>(endpoints.billableServices).then((r) => r.data),
    staleTime: STALE.REFERENCE,
  });

  const columns: ColumnDef<BillableService>[] = [
    { header: 'Service name', accessorKey: 'name' },
    { header: 'Code', cell: ({ row }) => row.original.code ? (
      <code className="text-xs font-mono bg-gray-100 px-1.5 py-0.5 rounded">{row.original.code}</code>
    ) : '—' },
    { header: 'Category', cell: ({ row }) => row.original.category ?? '—' },
    {
      header: 'Base price',
      cell: ({ row }) => <MoneyDisplay money={row.original.base_price} />,
    },
    {
      header: 'Effective from',
      cell: ({ row }) => format(new Date(row.original.effective_from), 'MMM d, yyyy'),
    },
    {
      header: 'Effective to',
      cell: ({ row }) => row.original.effective_to ? format(new Date(row.original.effective_to), 'MMM d, yyyy') : 'Ongoing',
    },
    {
      header: 'Active',
      cell: ({ row }) => (
        <Badge variant={row.original.is_active ? 'green' : 'gray'}>
          {row.original.is_active ? 'Active' : 'Inactive'}
        </Badge>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Price List</h1>
      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        emptyTitle="No services configured"
        emptyDescription="Billable services will appear here once added."
      />
    </div>
  );
}
