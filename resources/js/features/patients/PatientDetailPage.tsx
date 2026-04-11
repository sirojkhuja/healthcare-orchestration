import { useState } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';
import type { PatientSummary } from '@/types/api/patients';

const TABS = ['Summary', 'Timeline', 'Contacts', 'Documents', 'Insurance', 'Consents'] as const;
type Tab = (typeof TABS)[number];

export default function PatientDetailPage() {
  const { patientId } = useParams<{ patientId: string }>();
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<Tab>('Summary');

  const { data: patient, isLoading, error } = useQuery({
    queryKey: ['patients', 'summary', patientId],
    queryFn: () =>
      api.get<PatientSummary>(endpoints.patientSummary(patientId!)).then((r) => r.data),
    enabled: !!patientId,
  });

  if (isLoading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>;
  if (error) return <ApiErrorAlert error={error} />;
  if (!patient) return null;

  const age = new Date().getFullYear() - new Date(patient.date_of_birth).getFullYear();

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center gap-4">
        <button onClick={() => navigate('/patients')} className="text-gray-400 hover:text-gray-600">
          <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h1 className="text-2xl font-semibold text-gray-900">{patient.full_name}</h1>
        <Badge variant={patient.is_active ? 'green' : 'gray'}>
          {patient.is_active ? 'Active' : 'Inactive'}
        </Badge>
      </div>

      <div className="grid gap-6 lg:grid-cols-[280px,1fr]">
        {/* Patient card */}
        <div className="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-6">
          <div className="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-600">
            {patient.full_name.charAt(0)}
          </div>
          <div>
            <p className="font-semibold text-gray-900">{patient.full_name}</p>
            <p className="text-sm text-gray-500">{patient.date_of_birth} • {age} yrs</p>
          </div>
          {patient.primary_phone && (
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-gray-400">Phone</p>
              <p className="text-sm text-gray-700">{patient.primary_phone.number}</p>
            </div>
          )}
          {patient.email && (
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-gray-400">Email</p>
              <p className="text-sm text-gray-700 truncate">{patient.email}</p>
            </div>
          )}
          {patient.tags.length > 0 && (
            <div className="flex flex-wrap gap-1">
              {patient.tags.map((tag) => <Badge key={tag} variant="gray">{tag}</Badge>)}
            </div>
          )}
          <Button variant="secondary" size="sm" onClick={() => navigate(`/patients/${patientId}/edit`)}>
            Edit
          </Button>
        </div>

        {/* Tabs */}
        <div className="flex flex-col rounded-xl border border-gray-200 bg-white">
          <div className="flex border-b border-gray-200">
            {TABS.map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
                  activeTab === tab
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                {tab}
              </button>
            ))}
          </div>
          <div className="p-6">
            <PatientTabContent patientId={patientId!} tab={activeTab} patient={patient} />
          </div>
        </div>
      </div>
    </div>
  );
}

function PatientTabContent({ patientId, tab, patient }: { patientId: string; tab: Tab; patient: PatientSummary }) {
  switch (tab) {
    case 'Summary':
      return (
        <div className="flex flex-col gap-4">
          {patient.allergy_count > 0 && (
            <div className="rounded-md border border-red-200 bg-red-50 p-3">
              <p className="text-sm font-medium text-red-800">
                ⚠ {patient.allergy_count} known {patient.allergy_count === 1 ? 'allergy' : 'allergies'} on record
              </p>
            </div>
          )}
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="Upcoming Appointments" value={patient.upcoming_appointment_count} />
            <StatCard label="Active Prescriptions" value={patient.active_prescription_count} />
            <StatCard label="Open Claims" value={patient.open_claim_count} />
            <StatCard label="Allergies" value={patient.allergy_count} />
          </div>
        </div>
      );
    case 'Timeline':
      return <p className="text-sm text-gray-500">Timeline coming soon…</p>;
    case 'Contacts':
      return <PatientContacts patientId={patientId} />;
    case 'Documents':
      return <p className="text-sm text-gray-500">Documents coming soon…</p>;
    case 'Insurance':
      return <p className="text-sm text-gray-500">Insurance coming soon…</p>;
    case 'Consents':
      return <p className="text-sm text-gray-500">Consents coming soon…</p>;
    default:
      return null;
  }
}

function StatCard({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border border-gray-100 bg-gray-50 p-3 text-center">
      <p className="text-2xl font-bold text-gray-900">{value}</p>
      <p className="mt-1 text-xs text-gray-500">{label}</p>
    </div>
  );
}

function PatientContacts({ patientId }: { patientId: string }) {
  const { data, isLoading } = useQuery({
    queryKey: ['patients', 'contacts', patientId],
    queryFn: () => api.get(endpoints.patientContacts(patientId)).then((r) => r.data),
  });

  if (isLoading) return <Spinner />;
  const contacts = (data as { data?: unknown[] })?.data ?? [];
  if (contacts.length === 0) return <p className="text-sm text-gray-500">No contacts on record.</p>;

  return (
    <div className="flex flex-col gap-2">
      {contacts.map((c: unknown) => {
        const contact = c as Record<string, string>;
        return (
          <div key={contact['id']} className="flex justify-between rounded-lg border border-gray-100 p-3">
            <div>
              <p className="font-medium text-gray-900">{contact['name']}</p>
              <p className="text-sm text-gray-500">{contact['relationship']}</p>
            </div>
            <p className="text-sm text-gray-600">{contact['phone'] ?? contact['email'] ?? ''}</p>
          </div>
        );
      })}
    </div>
  );
}
