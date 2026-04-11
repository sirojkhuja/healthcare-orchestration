import { useParams, Link } from 'react-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';
import { Spinner } from '@/components/ui/Spinner';
import { StateMachineBadge } from '@/components/shared/StateMachineBadge';
import { generateIdempotencyKey } from '@/lib/api/idempotency';
import { STALE } from '@/lib/query/queryClient';
import type { LabOrder, LabTest, LabResult } from '@/types/api/lab';

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-blue-100 text-blue-700',
  in_progress: 'bg-amber-100 text-amber-700',
  completed: 'bg-green-100 text-green-700',
  canceled: 'bg-red-100 text-red-700',
};

const FLAG_VARIANTS: Record<string, 'red' | 'amber' | 'green' | 'gray'> = {
  normal: 'green',
  high: 'amber',
  low: 'amber',
  critical_high: 'red',
  critical_low: 'red',
};

export default function LabOrderDetailPage() {
  const { orderId } = useParams<{ orderId: string }>();
  const qc = useQueryClient();

  const { data: order, isLoading } = useQuery({
    queryKey: ['lab-orders', 'detail', orderId],
    queryFn: () => api.get<LabOrder>(endpoints.labOrder(orderId!)).then((r) => r.data),
    staleTime: STALE.DETAIL,
    enabled: !!orderId,
  });

  const { data: results } = useQuery({
    queryKey: ['lab-orders', 'results', orderId],
    queryFn: () => api.get<LabResult[]>(endpoints.labOrderResults(orderId!)).then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: !!orderId,
  });

  const { mutate: doAction, isPending } = useMutation({
    mutationFn: (action: 'submit' | 'cancel') =>
      api.post(endpoints.labOrderAction(orderId!, action), {}, {
        headers: { 'Idempotency-Key': generateIdempotencyKey() },
      }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['lab-orders', 'detail', orderId] }),
  });

  const testColumns: ColumnDef<LabTest>[] = [
    { header: 'Test name', accessorKey: 'test_name' },
    { header: 'Panel', cell: ({ row }) => row.original.panel_name ?? '—' },
    { header: 'Code', cell: ({ row }) => row.original.test_code ? (
      <code className="text-xs font-mono bg-gray-100 px-1.5 py-0.5 rounded">{row.original.test_code}</code>
    ) : '—' },
    {
      header: 'Status',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'completed' ? 'green' : row.original.status === 'canceled' ? 'red' : 'blue'} className="capitalize">
          {row.original.status}
        </Badge>
      ),
    },
  ];

  const resultColumns: ColumnDef<LabResult>[] = [
    { header: 'Test', cell: ({ row }) => row.original.test_id.slice(0, 8) },
    { header: 'Value', cell: ({ row }) => <span className="font-semibold text-gray-900">{row.original.value}</span> },
    { header: 'Unit', cell: ({ row }) => row.original.unit ?? '—' },
    { header: 'Reference', cell: ({ row }) => row.original.reference_range ?? '—' },
    {
      header: 'Flag',
      cell: ({ row }) => row.original.flag ? (
        <Badge variant={FLAG_VARIANTS[row.original.flag] ?? 'gray'} className="uppercase">
          {row.original.flag.replace('_', ' ')}
        </Badge>
      ) : '—',
    },
    { header: 'Received', cell: ({ row }) => format(new Date(row.original.received_at), 'MMM d, yyyy') },
  ];

  if (isLoading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>;
  if (!order) return <p className="text-center text-gray-500 py-16">Lab order not found.</p>;

  return (
    <div className="flex flex-col gap-6">
      <nav className="text-sm text-gray-500">
        <Link to="/lab-orders" className="hover:underline">Lab Orders</Link>
        <span className="mx-2">/</span>
        <span className="text-gray-900">{order.patient_name}</span>
      </nav>

      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div className="flex items-start justify-between">
          <div>
            <Link to={`/patients/${order.patient_id}`} className="text-xl font-semibold text-blue-600 hover:underline">
              {order.patient_name}
            </Link>
            <p className="text-sm text-gray-500 mt-1">Ordered by {order.provider_name}</p>
          </div>
          <div className="flex items-center gap-3">
            <StateMachineBadge status={order.status} colorMap={STATUS_COLORS} />
            {order.status === 'draft' && (
              <Button size="sm" isLoading={isPending} onClick={() => doAction('submit')}>Submit</Button>
            )}
            {(order.status === 'draft' || order.status === 'submitted') && (
              <Button size="sm" variant="danger" isLoading={isPending} onClick={() => doAction('cancel')}>Cancel</Button>
            )}
          </div>
        </div>

        <dl className="mt-4 grid grid-cols-3 gap-3 text-sm">
          <div>
            <dt className="text-gray-500">Ordered</dt>
            <dd className="font-medium text-gray-900">{format(new Date(order.ordered_at), 'MMM d, yyyy')}</dd>
          </div>
          {order.submitted_at && (
            <div>
              <dt className="text-gray-500">Submitted</dt>
              <dd className="font-medium text-gray-900">{format(new Date(order.submitted_at), 'MMM d, yyyy')}</dd>
            </div>
          )}
          {order.completed_at && (
            <div>
              <dt className="text-gray-500">Completed</dt>
              <dd className="font-medium text-gray-900">{format(new Date(order.completed_at), 'MMM d, yyyy')}</dd>
            </div>
          )}
        </dl>
        {order.notes && (
          <p className="mt-3 text-sm text-gray-600 border-t border-gray-100 pt-3">{order.notes}</p>
        )}
      </div>

      <div>
        <h2 className="mb-3 font-semibold text-gray-900">Tests ({order.tests_count})</h2>
        <DataTable
          columns={testColumns}
          data={order.tests ?? []}
          isLoading={false}
          emptyTitle="No tests"
        />
      </div>

      {(results ?? []).length > 0 && (
        <div>
          <h2 className="mb-3 font-semibold text-gray-900">Results ({results?.length})</h2>
          <DataTable
            columns={resultColumns}
            data={results ?? []}
            isLoading={false}
            emptyTitle="No results yet"
          />
        </div>
      )}
    </div>
  );
}
