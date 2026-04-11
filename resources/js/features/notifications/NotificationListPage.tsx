import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/Badge';
import { STALE } from '@/lib/query/queryClient';
import type { PaginatedResponse } from '@/types/common';

interface NotificationRow {
  id: string;
  recipient_name: string;
  recipient_contact: string;
  template_name: string;
  channel: 'email' | 'sms' | 'telegram';
  status: 'queued' | 'sent' | 'failed';
  scheduled_at?: string;
  sent_at?: string;
  error_message?: string;
}

const CHANNEL_COLORS: Record<string, string> = {
  email: 'bg-blue-100 text-blue-700',
  sms: 'bg-green-100 text-green-700',
  telegram: 'bg-indigo-100 text-indigo-700',
};

export default function NotificationListPage() {
  const [page, setPage] = useState(1);
  const [channelFilter, setChannelFilter] = useState<'all' | 'email' | 'sms' | 'telegram'>('all');
  const [statusFilter, setStatusFilter] = useState<'all' | 'queued' | 'sent' | 'failed'>('all');

  const { data, isLoading } = useQuery({
    queryKey: ['notifications', 'list', page, channelFilter, statusFilter],
    queryFn: () =>
      api.get<PaginatedResponse<NotificationRow>>(endpoints.notifications, {
        params: {
          page,
          per_page: 25,
          channel: channelFilter === 'all' ? undefined : channelFilter,
          status: statusFilter === 'all' ? undefined : statusFilter,
        },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<NotificationRow>[] = [
    {
      header: 'Recipient',
      cell: ({ row }) => (
        <div>
          <p className="text-sm font-medium text-gray-900">{row.original.recipient_name}</p>
          <p className="text-xs text-gray-400">{row.original.recipient_contact}</p>
        </div>
      ),
    },
    { header: 'Template', accessorKey: 'template_name' },
    {
      header: 'Channel',
      cell: ({ row }) => (
        <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${CHANNEL_COLORS[row.original.channel] ?? ''}`}>
          {row.original.channel}
        </span>
      ),
    },
    {
      header: 'Scheduled',
      cell: ({ row }) => row.original.scheduled_at ? format(new Date(row.original.scheduled_at), 'MMM d, h:mm a') : '—',
    },
    {
      header: 'Sent',
      cell: ({ row }) => row.original.sent_at ? format(new Date(row.original.sent_at), 'MMM d, h:mm a') : '—',
    },
    {
      header: 'Status',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'sent' ? 'green' : row.original.status === 'failed' ? 'red' : 'gray'} className="capitalize">
          {row.original.status}
        </Badge>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Notifications</h1>

      <div className="flex flex-wrap gap-3">
        <div className="flex gap-1.5">
          {(['all', 'email', 'sms', 'telegram'] as const).map((c) => (
            <button
              key={c}
              onClick={() => setChannelFilter(c)}
              className={`rounded-full px-3 py-1 text-sm font-medium capitalize transition-colors ${channelFilter === c ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
            >
              {c}
            </button>
          ))}
        </div>
        <div className="flex gap-1.5">
          {(['all', 'queued', 'sent', 'failed'] as const).map((s) => (
            <button
              key={s}
              onClick={() => setStatusFilter(s)}
              className={`rounded-full px-3 py-1 text-sm font-medium capitalize transition-colors ${statusFilter === s ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
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
        emptyTitle="No notifications found"
        emptyDescription="Notifications will appear here once sent."
      />
    </div>
  );
}
