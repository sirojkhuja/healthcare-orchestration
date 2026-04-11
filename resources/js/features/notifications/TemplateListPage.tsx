import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/Badge';
import { STALE } from '@/lib/query/queryClient';
import type { PaginatedResponse } from '@/types/common';

interface NotificationTemplate {
  id: string;
  name: string;
  channel: 'email' | 'sms' | 'telegram';
  event_trigger: string;
  subject?: string;
  is_active: boolean;
}

const CHANNEL_COLORS: Record<string, string> = {
  email: 'bg-blue-100 text-blue-700',
  sms: 'bg-green-100 text-green-700',
  telegram: 'bg-indigo-100 text-indigo-700',
};

export default function TemplateListPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['notification-templates', 'list'],
    queryFn: () =>
      api.get<PaginatedResponse<NotificationTemplate>>(endpoints.notificationTemplates).then((r) => r.data),
    staleTime: STALE.REFERENCE,
  });

  const columns: ColumnDef<NotificationTemplate>[] = [
    { header: 'Template name', accessorKey: 'name' },
    {
      header: 'Channel',
      cell: ({ row }) => (
        <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${CHANNEL_COLORS[row.original.channel] ?? ''}`}>
          {row.original.channel}
        </span>
      ),
    },
    { header: 'Trigger event', accessorKey: 'event_trigger' },
    { header: 'Subject', cell: ({ row }) => row.original.subject ?? '—' },
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
      <h1 className="text-2xl font-semibold text-gray-900">Notification Templates</h1>
      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        emptyTitle="No templates configured"
        emptyDescription="Notification templates will appear here once created."
      />
    </div>
  );
}
