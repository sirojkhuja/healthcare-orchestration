import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/Badge';
import { STALE } from '@/lib/query/queryClient';
import type { Clinic } from '@/types/api/tenant';
import type { PaginatedResponse } from '@/types/common';

export default function ClinicsPage() {
  const [selectedClinicId, setSelectedClinicId] = useState<string | null>(null);
  const [clinicTab, setClinicTab] = useState<'departments' | 'rooms'>('departments');

  const { data: clinics, isLoading } = useQuery({
    queryKey: ['clinics', 'list'],
    queryFn: () => api.get<PaginatedResponse<Clinic>>(endpoints.clinics).then((r) => r.data),
    staleTime: STALE.REFERENCE,
  });

  const { data: departments } = useQuery({
    queryKey: ['clinics', 'departments', selectedClinicId],
    queryFn: () =>
      api.get<{ data: { id: string; name: string; floor?: string }[] }>(
        endpoints.clinicDepartments(selectedClinicId!)
      ).then((r) => r.data),
    staleTime: STALE.REFERENCE,
    enabled: !!selectedClinicId && clinicTab === 'departments',
  });

  const { data: rooms } = useQuery({
    queryKey: ['clinics', 'rooms', selectedClinicId],
    queryFn: () =>
      api.get<{ data: { id: string; name: string; room_type?: string; capacity?: number }[] }>(
        endpoints.clinicRooms(selectedClinicId!)
      ).then((r) => r.data),
    staleTime: STALE.REFERENCE,
    enabled: !!selectedClinicId && clinicTab === 'rooms',
  });

  const selectedClinic = clinics?.data.find((c) => c.id === selectedClinicId);

  const clinicColumns: ColumnDef<Clinic>[] = [
    { header: 'Name', accessorKey: 'name', cell: ({ getValue }) => <span className="font-medium text-gray-900">{getValue<string>()}</span> },
    { header: 'Address', cell: ({ row }) => [row.original.address?.city, row.original.address?.country].filter(Boolean).join(', ') || '—' },
    { header: 'Phone', cell: ({ row }) => row.original.contact_phone ?? '—' },
    {
      header: 'Active',
      cell: ({ row }) => <Badge variant={row.original.is_active ? 'green' : 'gray'}>{row.original.is_active ? 'Active' : 'Inactive'}</Badge>,
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Clinics</h1>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Clinic list */}
        <DataTable
          columns={clinicColumns}
          data={clinics?.data ?? []}
          isLoading={isLoading}
          onRowClick={(row) => { setSelectedClinicId(row.id); setClinicTab('departments'); }}
          emptyTitle="No clinics found"
          emptyDescription="Clinics will appear here once created."
        />

        {/* Clinic detail */}
        {selectedClinic && (
          <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div className="border-b border-gray-100 px-5 py-4">
              <h2 className="font-semibold text-gray-900">{selectedClinic.name}</h2>
              <p className="text-sm text-gray-500">{selectedClinic.address?.city}</p>
            </div>
            <div className="border-b border-gray-200">
              <nav className="flex gap-4 px-5">
                {(['departments', 'rooms'] as const).map((t) => (
                  <button
                    key={t}
                    onClick={() => setClinicTab(t)}
                    className={`py-3 text-sm font-medium border-b-2 capitalize transition-colors ${clinicTab === t ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                  >
                    {t}
                  </button>
                ))}
              </nav>
            </div>
            <div className="divide-y divide-gray-100">
              {clinicTab === 'departments' && (departments?.data ?? []).map((d) => (
                <div key={d.id} className="flex items-center justify-between px-5 py-3">
                  <span className="text-sm font-medium text-gray-900">{d.name}</span>
                  {d.floor && <span className="text-xs text-gray-400">Floor {d.floor}</span>}
                </div>
              ))}
              {clinicTab === 'rooms' && (rooms?.data ?? []).map((r) => (
                <div key={r.id} className="flex items-center justify-between px-5 py-3">
                  <span className="text-sm font-medium text-gray-900">{r.name}</span>
                  <div className="flex items-center gap-2">
                    {r.room_type && <Badge variant="gray" className="capitalize">{r.room_type}</Badge>}
                    {r.capacity && <span className="text-xs text-gray-400">Cap: {r.capacity}</span>}
                  </div>
                </div>
              ))}
              {clinicTab === 'departments' && (departments?.data ?? []).length === 0 && (
                <p className="px-5 py-8 text-center text-sm text-gray-400">No departments.</p>
              )}
              {clinicTab === 'rooms' && (rooms?.data ?? []).length === 0 && (
                <p className="px-5 py-8 text-center text-sm text-gray-400">No rooms.</p>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
