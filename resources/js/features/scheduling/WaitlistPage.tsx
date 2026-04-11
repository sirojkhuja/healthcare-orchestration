import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { generateIdempotencyKey } from '@/lib/api/idempotency';
import { STALE } from '@/lib/query/queryClient';
import type { WaitlistEntry } from '@/types/api/appointments';
import type { PaginatedResponse } from '@/types/common';

export default function WaitlistPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [offerEntry, setOfferEntry] = useState<WaitlistEntry | null>(null);
  const [slotStart, setSlotStart] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['waitlist', 'list', page],
    queryFn: () =>
      api.get<PaginatedResponse<WaitlistEntry>>(endpoints.waitlist, { params: { page, per_page: 25 } })
        .then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const { mutate: offerSlot, isPending: offering } = useMutation({
    mutationFn: ({ entryId, start_at }: { entryId: string; start_at: string }) =>
      api.post(endpoints.waitlistOfferSlot(entryId), { slot: { start_at } }, {
        headers: { 'Idempotency-Key': generateIdempotencyKey() },
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['waitlist'] });
      setOfferEntry(null);
      setSlotStart('');
    },
  });

  const columns: ColumnDef<WaitlistEntry>[] = [
    {
      header: 'Patient',
      cell: ({ row }) => (
        <span className="font-medium text-gray-900">{row.original.patient_name}</span>
      ),
    },
    {
      header: 'Requested provider',
      cell: ({ row }) => row.original.provider_name ?? <span className="text-gray-400">Any</span>,
    },
    {
      header: 'Preferred dates',
      cell: ({ row }) => {
        const from = row.original.preferred_date_from;
        const to = row.original.preferred_date_to;
        if (!from) return <span className="text-gray-400">Flexible</span>;
        return (
          <span className="text-sm text-gray-700">
            {format(new Date(from), 'MMM d')} – {to ? format(new Date(to), 'MMM d') : '…'}
          </span>
        );
      },
    },
    {
      header: 'Waiting',
      cell: ({ row }) => (
        <Badge variant={row.original.days_waiting > 14 ? 'red' : row.original.days_waiting > 7 ? 'amber' : 'gray'}>
          {row.original.days_waiting}d
        </Badge>
      ),
    },
    { header: 'Notes', cell: ({ row }) => row.original.notes ?? '—' },
    {
      header: '',
      id: 'actions',
      cell: ({ row }) => (
        <Button size="sm" onClick={() => setOfferEntry(row.original)}>
          Offer slot
        </Button>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Waitlist</h1>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        pagination={data ? {
          page,
          pageSize: 25,
          total: data.meta.total,
          onPageChange: setPage,
        } : undefined}
        emptyTitle="Waitlist is empty"
        emptyDescription="No patients are currently on the waitlist."
      />

      <Modal
        isOpen={!!offerEntry}
        onClose={() => { setOfferEntry(null); setSlotStart(''); }}
        title={`Offer slot to ${offerEntry?.patient_name}`}
      >
        <div className="flex flex-col gap-4">
          <p className="text-sm text-gray-600">
            Select a date and time to offer to this patient.
          </p>
          <Input
            label="Slot date & time"
            type="datetime-local"
            value={slotStart}
            onChange={(e) => setSlotStart(e.target.value)}
          />
          <div className="flex justify-end gap-3">
            <Button variant="secondary" onClick={() => { setOfferEntry(null); setSlotStart(''); }}>
              Cancel
            </Button>
            <Button
              isLoading={offering}
              disabled={!slotStart}
              onClick={() => offerEntry && offerSlot({ entryId: offerEntry.id, start_at: slotStart })}
            >
              Offer slot
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
