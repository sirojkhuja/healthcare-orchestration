import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { STALE } from '@/lib/query/queryClient';
import type { PaginatedResponse } from '@/types/common';

interface AuditEvent {
  id: string;
  object_type: string;
  object_id: string;
  action: string;
  actor_id: string;
  actor_name: string;
  created_at: string;
  before_data?: unknown;
  after_data?: unknown;
  request_id?: string;
  correlation_id?: string;
}

const ACTION_COLORS: Record<string, 'green' | 'amber' | 'red' | 'blue' | 'gray'> = {
  created: 'green',
  updated: 'blue',
  deleted: 'red',
  approved: 'green',
  denied: 'red',
  submitted: 'amber',
};

export default function AuditLogPage() {
  const [page, setPage] = useState(1);
  const [objectType, setObjectType] = useState('');
  const [action, setAction] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [expanded, setExpanded] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['audit-events', 'list', page, objectType, action, dateFrom, dateTo],
    queryFn: () =>
      api.get<PaginatedResponse<AuditEvent>>(endpoints.auditEvents, {
        params: {
          page,
          per_page: 25,
          object_type: objectType || undefined,
          action: action || undefined,
          date_from: dateFrom || undefined,
          date_to: dateTo || undefined,
        },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<AuditEvent>[] = [
    {
      header: 'Timestamp',
      cell: ({ row }) => (
        <span className="text-xs font-mono text-gray-600">
          {format(new Date(row.original.created_at), 'MMM d, yyyy HH:mm:ss')}
        </span>
      ),
    },
    { header: 'Actor', cell: ({ row }) => <span className="font-medium text-gray-900">{row.original.actor_name}</span> },
    {
      header: 'Action',
      cell: ({ row }) => (
        <Badge variant={ACTION_COLORS[row.original.action] ?? 'gray'} className="capitalize">
          {row.original.action}
        </Badge>
      ),
    },
    { header: 'Object type', cell: ({ row }) => <span className="capitalize text-gray-700">{row.original.object_type}</span> },
    {
      header: 'Object ID',
      cell: ({ row }) => (
        <code className="text-xs font-mono text-gray-500">{row.original.object_id.slice(0, 8)}…</code>
      ),
    },
    {
      header: '',
      id: 'expand',
      cell: ({ row }) => (
        <button
          onClick={(e) => {
            e.stopPropagation();
            setExpanded((prev) => (prev === row.original.id ? null : row.original.id));
          }}
          className="text-xs text-blue-600 hover:underline"
        >
          {expanded === row.original.id ? 'Collapse' : 'Details'}
        </button>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Audit Log</h1>
        <Button
          variant="secondary"
          onClick={() => window.open(endpoints.auditExport, '_blank')}
        >
          Export CSV
        </Button>
      </div>

      {/* Filters */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Input
          placeholder="Object type (e.g. appointment)"
          value={objectType}
          onChange={(e) => { setObjectType(e.target.value); setPage(1); }}
        />
        <Input
          placeholder="Action (e.g. created)"
          value={action}
          onChange={(e) => { setAction(e.target.value); setPage(1); }}
        />
        <Input
          type="date"
          label="From"
          value={dateFrom}
          onChange={(e) => { setDateFrom(e.target.value); setPage(1); }}
        />
        <Input
          type="date"
          label="To"
          value={dateTo}
          onChange={(e) => { setDateTo(e.target.value); setPage(1); }}
        />
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        pagination={data ? { page, pageSize: 25, total: data.meta.total, onPageChange: setPage } : undefined}
        emptyTitle="No audit events found"
        emptyDescription="Try adjusting your filters."
        renderSubRow={(row) =>
          expanded === row.id ? (
            <tr>
              <td colSpan={6} className="bg-gray-50 px-5 py-4">
                <div className="grid grid-cols-2 gap-4 text-xs">
                  {row.before_data != null && (
                    <div>
                      <p className="font-semibold text-gray-600 mb-1">Before</p>
                      <pre className="bg-white border border-gray-200 rounded p-3 overflow-x-auto text-gray-700">
                        {JSON.stringify(row.before_data, null, 2)}
                      </pre>
                    </div>
                  )}
                  {row.after_data != null && (
                    <div>
                      <p className="font-semibold text-gray-600 mb-1">After</p>
                      <pre className="bg-white border border-gray-200 rounded p-3 overflow-x-auto text-gray-700">
                        {JSON.stringify(row.after_data, null, 2)}
                      </pre>
                    </div>
                  )}
                  <div>
                    <p className="font-semibold text-gray-600 mb-1">Request ID</p>
                    <code className="text-gray-500">{row.request_id ?? '—'}</code>
                  </div>
                  <div>
                    <p className="font-semibold text-gray-600 mb-1">Correlation ID</p>
                    <code className="text-gray-500">{row.correlation_id ?? '—'}</code>
                  </div>
                </div>
              </td>
            </tr>
          ) : null
        }
      />
    </div>
  );
}
