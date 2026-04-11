import { useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Spinner } from '@/components/ui/Spinner';
import { AppointmentStatusBadge } from '@/components/shared/StateMachineBadge';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';
import { generateIdempotencyKey } from '@/lib/api/idempotency';
import { STALE } from '@/lib/query/queryClient';
import type {
  Appointment, AppointmentNote, AppointmentParticipant,
  AppointmentStatus, AppointmentTransitionPayload, VALID_TRANSITIONS,
} from '@/types/api/appointments';
import type { PaginatedResponse } from '@/types/common';

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  scheduled: 'bg-blue-100 text-blue-700',
  confirmed: 'bg-indigo-100 text-indigo-700',
  checked_in: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-orange-100 text-orange-700',
  completed: 'bg-green-100 text-green-700',
  canceled: 'bg-red-100 text-red-700',
  no_show: 'bg-red-100 text-red-700',
  rescheduled: 'bg-purple-100 text-purple-700',
};

const ACTION_LABELS: Partial<Record<AppointmentStatus, string>> = {
  scheduled: 'Schedule',
  confirmed: 'Confirm',
  checked_in: 'Check In',
  in_progress: 'Start',
  completed: 'Complete',
  canceled: 'Cancel',
  no_show: 'No Show',
  rescheduled: 'Reschedule',
};

const TRANSITIONS: Record<AppointmentStatus, AppointmentStatus[]> = {
  draft: ['scheduled'],
  scheduled: ['confirmed', 'canceled', 'no_show', 'rescheduled'],
  confirmed: ['checked_in', 'canceled', 'no_show', 'rescheduled'],
  checked_in: ['in_progress'],
  in_progress: ['completed'],
  completed: [],
  canceled: ['scheduled'],
  no_show: ['scheduled'],
  rescheduled: ['scheduled'],
};

export default function AppointmentDetailPage() {
  const { appointmentId } = useParams<{ appointmentId: string }>();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const [tab, setTab] = useState<'participants' | 'notes' | 'audit'>('notes');
  const [confirmAction, setConfirmAction] = useState<AppointmentStatus | null>(null);
  const [reason, setReason] = useState('');
  const [noteContent, setNoteContent] = useState('');

  const { data: appt, isLoading } = useQuery({
    queryKey: ['appointments', 'detail', appointmentId],
    queryFn: () => api.get<Appointment>(endpoints.appointment(appointmentId!)).then((r) => r.data),
    staleTime: STALE.REALTIME,
    enabled: !!appointmentId,
  });

  const { data: notes } = useQuery({
    queryKey: ['appointments', 'notes', appointmentId],
    queryFn: () =>
      api.get<AppointmentNote[]>(endpoints.appointmentNotes(appointmentId!)).then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: !!appointmentId && tab === 'notes',
  });

  const { data: participants } = useQuery({
    queryKey: ['appointments', 'participants', appointmentId],
    queryFn: () =>
      api.get<AppointmentParticipant[]>(endpoints.appointmentParticipants(appointmentId!)).then((r) => r.data),
    staleTime: STALE.LIST,
    enabled: !!appointmentId && tab === 'participants',
  });

  const { data: auditLog } = useQuery({
    queryKey: ['appointments', 'audit', appointmentId],
    queryFn: () =>
      api.get<{ data: { action: string; actor_name: string; created_at: string }[] }>(
        endpoints.appointmentAudit(appointmentId!)
      ).then((r) => r.data),
    staleTime: STALE.DETAIL,
    enabled: !!appointmentId && tab === 'audit',
  });

  const { mutate: transition, isPending: transitioning, error: transitionError } = useMutation({
    mutationFn: ({ action, payload }: { action: AppointmentStatus; payload: AppointmentTransitionPayload }) =>
      api.post(endpoints.appointmentAction(appointmentId!, action), payload, {
        headers: { 'Idempotency-Key': generateIdempotencyKey() },
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['appointments', 'detail', appointmentId] });
      qc.invalidateQueries({ queryKey: ['appointments', 'list'] });
      setConfirmAction(null);
      setReason('');
    },
  });

  const { mutate: addNote, isPending: addingNote } = useMutation({
    mutationFn: (content: string) =>
      api.post(endpoints.appointmentNotes(appointmentId!), { content }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['appointments', 'notes', appointmentId] });
      setNoteContent('');
    },
  });

  if (isLoading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>;
  if (!appt) return <p className="text-center text-gray-500 py-16">Appointment not found.</p>;

  const availableActions = TRANSITIONS[appt.status] ?? [];

  return (
    <div className="flex flex-col gap-6">
      {/* Breadcrumb */}
      <nav className="text-sm text-gray-500">
        <Link to="/appointments" className="hover:underline">Appointments</Link>
        <span className="mx-2">/</span>
        <span className="text-gray-900">{appt.patient_name}</span>
      </nav>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Main card */}
        <div className="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <div className="flex items-start justify-between">
            <div>
              <Link to={`/patients/${appt.patient_id}`} className="text-xl font-semibold text-blue-600 hover:underline">
                {appt.patient_name}
              </Link>
              <p className="text-sm text-gray-500 mt-1">
                with{' '}
                <Link to={`/providers/${appt.provider_id}`} className="text-blue-600 hover:underline">
                  {appt.provider_name}
                </Link>
              </p>
            </div>
            <AppointmentStatusBadge status={appt.status} />
          </div>

          <dl className="mt-5 grid grid-cols-2 gap-4 text-sm">
            <div>
              <dt className="text-gray-500">Date</dt>
              <dd className="font-medium text-gray-900">
                {format(new Date(appt.scheduled_start_at), 'EEEE, MMMM d, yyyy')}
              </dd>
            </div>
            <div>
              <dt className="text-gray-500">Time</dt>
              <dd className="font-medium text-gray-900">
                {format(new Date(appt.scheduled_start_at), 'h:mm a')} – {format(new Date(appt.scheduled_end_at), 'h:mm a')}
                <span className="text-gray-400 ml-1">({appt.timezone})</span>
              </dd>
            </div>
            <div>
              <dt className="text-gray-500">Clinic</dt>
              <dd className="font-medium text-gray-900">{appt.clinic_name}</dd>
            </div>
            {appt.room_name && (
              <div>
                <dt className="text-gray-500">Room</dt>
                <dd className="font-medium text-gray-900">{appt.room_name}</dd>
              </div>
            )}
            <div>
              <dt className="text-gray-500">Type</dt>
              <dd className="font-medium text-gray-900 capitalize">{appt.appointment_type.replace('_', ' ')}</dd>
            </div>
            {appt.service_reason && (
              <div className="col-span-2">
                <dt className="text-gray-500">Reason</dt>
                <dd className="font-medium text-gray-900">{appt.service_reason}</dd>
              </div>
            )}
          </dl>
        </div>

        {/* Actions panel */}
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm flex flex-col gap-4">
          <h2 className="font-semibold text-gray-900">Actions</h2>

          {transitionError && <ApiErrorAlert error={transitionError} />}

          {availableActions.length === 0 ? (
            <p className="text-sm text-gray-400">No further actions available.</p>
          ) : (
            <div className="flex flex-col gap-2">
              {availableActions.map((action) => (
                <Button
                  key={action}
                  variant={action === 'canceled' || action === 'no_show' ? 'danger' : 'primary'}
                  onClick={() => setConfirmAction(action)}
                  isLoading={transitioning && confirmAction === action}
                  className="w-full justify-center"
                >
                  {ACTION_LABELS[action] ?? action}
                </Button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200">
        <nav className="-mb-px flex gap-6">
          {(['notes', 'participants', 'audit'] as const).map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`py-3 text-sm font-medium border-b-2 transition-colors capitalize ${
                tab === t ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              {t}
            </button>
          ))}
        </nav>
      </div>

      {/* Notes */}
      {tab === 'notes' && (
        <div className="flex flex-col gap-4">
          <div className="flex gap-3">
            <div className="flex-1">
              <Input
                placeholder="Add a note…"
                value={noteContent}
                onChange={(e) => setNoteContent(e.target.value)}
              />
            </div>
            <Button onClick={() => noteContent && addNote(noteContent)} isLoading={addingNote}>
              Add
            </Button>
          </div>
          <div className="flex flex-col gap-3">
            {(notes ?? []).length === 0 && (
              <p className="text-sm text-gray-400 text-center py-4">No notes yet.</p>
            )}
            {(notes ?? []).map((note) => (
              <div key={note.id} className="rounded-lg border border-gray-100 bg-white p-4">
                <p className="text-sm text-gray-900">{note.content}</p>
                <p className="mt-2 text-xs text-gray-400">
                  {note.author_name} · {format(new Date(note.created_at), 'MMM d, yyyy h:mm a')}
                </p>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Participants */}
      {tab === 'participants' && (
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          {(participants ?? []).length === 0 && (
            <p className="px-5 py-8 text-center text-sm text-gray-400">No additional participants.</p>
          )}
          <ul className="divide-y divide-gray-100">
            {(participants ?? []).map((p) => (
              <li key={p.id} className="flex items-center justify-between px-5 py-3">
                <span className="text-sm font-medium text-gray-900">{p.name}</span>
                <Badge variant="gray">{p.role}</Badge>
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Audit trail */}
      {tab === 'audit' && (
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          <ul className="divide-y divide-gray-100">
            {(auditLog?.data ?? []).length === 0 && (
              <li className="px-5 py-8 text-center text-sm text-gray-400">No audit events found.</li>
            )}
            {(auditLog?.data ?? []).map((event, i) => (
              <li key={i} className="flex items-center justify-between px-5 py-3 text-sm">
                <span className="font-medium text-gray-900 capitalize">{event.action}</span>
                <span className="text-gray-500">{event.actor_name}</span>
                <span className="text-gray-400">{format(new Date(event.created_at), 'MMM d, h:mm a')}</span>
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Confirm transition modal */}
      <Modal
        isOpen={confirmAction !== null}
        onClose={() => { setConfirmAction(null); setReason(''); }}
        title={`${ACTION_LABELS[confirmAction!] ?? confirmAction} appointment`}
      >
        <div className="flex flex-col gap-4">
          <p className="text-sm text-gray-600">
            Are you sure you want to <strong>{ACTION_LABELS[confirmAction!]?.toLowerCase() ?? confirmAction}</strong> this appointment?
          </p>
          {(confirmAction === 'canceled' || confirmAction === 'no_show' || confirmAction === 'rescheduled') && (
            <Input
              label="Reason (optional)"
              placeholder="Enter reason…"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
            />
          )}
          <div className="flex justify-end gap-3">
            <Button variant="secondary" onClick={() => { setConfirmAction(null); setReason(''); }}>
              Cancel
            </Button>
            <Button
              variant={confirmAction === 'canceled' || confirmAction === 'no_show' ? 'danger' : 'primary'}
              isLoading={transitioning}
              onClick={() => confirmAction && transition({ action: confirmAction, payload: { reason: reason || undefined } })}
            >
              Confirm
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
