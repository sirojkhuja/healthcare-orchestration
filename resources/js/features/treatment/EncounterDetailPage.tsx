import { useState } from 'react';
import { useParams, Link } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Badge } from '@/components/ui/Badge';
import { DataTable } from '@/components/ui/DataTable';
import { Spinner } from '@/components/ui/Spinner';
import { STALE } from '@/lib/query/queryClient';
import type { Encounter, Diagnosis, Procedure } from '@/types/api/treatment';

export default function EncounterDetailPage() {
  const { encounterId } = useParams<{ encounterId: string }>();
  const [tab, setTab] = useState<'diagnoses' | 'procedures'>('diagnoses');

  const { data: encounter, isLoading } = useQuery({
    queryKey: ['encounters', 'detail', encounterId],
    queryFn: () => api.get<Encounter>(endpoints.encounter(encounterId!)).then((r) => r.data),
    staleTime: STALE.DETAIL,
    enabled: !!encounterId,
  });

  const { data: diagnoses } = useQuery({
    queryKey: ['encounters', 'diagnoses', encounterId],
    queryFn: () => api.get<Diagnosis[]>(endpoints.encounterDiagnoses(encounterId!)).then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: !!encounterId && tab === 'diagnoses',
  });

  const { data: procedures } = useQuery({
    queryKey: ['encounters', 'procedures', encounterId],
    queryFn: () => api.get<Procedure[]>(endpoints.encounterProcedures(encounterId!)).then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: !!encounterId && tab === 'procedures',
  });

  const diagnosisColumns: ColumnDef<Diagnosis>[] = [
    { header: 'ICD Code', accessorKey: 'icd_code', cell: ({ getValue }) => <code className="text-sm font-mono bg-gray-100 px-1.5 py-0.5 rounded">{getValue<string>()}</code> },
    { header: 'Description', accessorKey: 'description' },
    {
      header: 'Type',
      cell: ({ row }) => (
        <Badge variant={row.original.type === 'primary' ? 'blue' : 'gray'} className="capitalize">
          {row.original.type}
        </Badge>
      ),
    },
    {
      header: 'Severity',
      cell: ({ row }) => row.original.severity ? (
        <Badge variant={row.original.severity === 'severe' ? 'red' : row.original.severity === 'moderate' ? 'amber' : 'green'} className="capitalize">
          {row.original.severity}
        </Badge>
      ) : '—',
    },
  ];

  const procedureColumns: ColumnDef<Procedure>[] = [
    { header: 'CPT Code', accessorKey: 'cpt_code', cell: ({ getValue }) => <code className="text-sm font-mono bg-gray-100 px-1.5 py-0.5 rounded">{getValue<string>()}</code> },
    { header: 'Description', accessorKey: 'description' },
    { header: 'Qty', accessorKey: 'quantity' },
    {
      header: 'Status',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'performed' ? 'green' : row.original.status === 'canceled' ? 'red' : 'gray'} className="capitalize">
          {row.original.status}
        </Badge>
      ),
    },
    {
      header: 'Performed at',
      cell: ({ row }) => row.original.performed_at ? format(new Date(row.original.performed_at), 'MMM d, yyyy') : '—',
    },
  ];

  if (isLoading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>;
  if (!encounter) return <p className="text-center text-gray-500 py-16">Encounter not found.</p>;

  return (
    <div className="flex flex-col gap-6">
      <nav className="text-sm text-gray-500">
        <Link to="/encounters" className="hover:underline">Encounters</Link>
        <span className="mx-2">/</span>
        <span className="text-gray-900">{format(new Date(encounter.occurred_at), 'MMM d, yyyy')}</span>
      </nav>

      {/* Header card */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div className="flex items-start justify-between">
          <div>
            <Link to={`/patients/${encounter.patient_id}`} className="text-xl font-semibold text-blue-600 hover:underline">
              {encounter.patient_name}
            </Link>
            <p className="text-sm text-gray-500 mt-1">
              with{' '}
              <Link to={`/providers/${encounter.provider_id}`} className="text-blue-600 hover:underline">
                {encounter.provider_name}
              </Link>
            </p>
          </div>
          <Badge variant={encounter.status === 'completed' ? 'green' : encounter.status === 'canceled' ? 'red' : 'blue'} className="capitalize">
            {encounter.status}
          </Badge>
        </div>
        <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
          <div>
            <dt className="text-gray-500">Date</dt>
            <dd className="font-medium text-gray-900">{format(new Date(encounter.occurred_at), 'EEEE, MMMM d, yyyy')}</dd>
          </div>
          <div>
            <dt className="text-gray-500">Type</dt>
            <dd className="font-medium text-gray-900 capitalize">{encounter.encounter_type}</dd>
          </div>
          {encounter.chief_complaint && (
            <div className="col-span-2">
              <dt className="text-gray-500">Chief complaint</dt>
              <dd className="font-medium text-gray-900">{encounter.chief_complaint}</dd>
            </div>
          )}
          {encounter.notes && (
            <div className="col-span-2">
              <dt className="text-gray-500">Notes</dt>
              <dd className="text-gray-700 whitespace-pre-line">{encounter.notes}</dd>
            </div>
          )}
        </dl>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex gap-6">
          {(['diagnoses', 'procedures'] as const).map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`py-3 text-sm font-medium border-b-2 transition-colors capitalize ${
                tab === t ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              {t} {t === 'diagnoses' ? `(${encounter.diagnoses_count})` : `(${encounter.procedures_count})`}
            </button>
          ))}
        </nav>
      </div>

      {tab === 'diagnoses' && (
        <DataTable
          columns={diagnosisColumns}
          data={diagnoses ?? []}
          isLoading={!diagnoses}
          emptyTitle="No diagnoses recorded"
          emptyDescription="Diagnoses will appear here once added to this encounter."
        />
      )}

      {tab === 'procedures' && (
        <DataTable
          columns={procedureColumns}
          data={procedures ?? []}
          isLoading={!procedures}
          emptyTitle="No procedures recorded"
          emptyDescription="Procedures will appear here once added to this encounter."
        />
      )}
    </div>
  );
}
