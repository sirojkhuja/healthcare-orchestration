import { useParams, Link } from 'react-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { ClaimStatusBadge } from '@/components/shared/StateMachineBadge';
import { MoneyDisplay } from '@/components/shared/MoneyDisplay';
import { generateIdempotencyKey } from '@/lib/api/idempotency';
import { STALE } from '@/lib/query/queryClient';
import type { Claim } from '@/types/api/insurance';

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-blue-100 text-blue-700',
  under_review: 'bg-amber-100 text-amber-700',
  approved: 'bg-green-100 text-green-700',
  denied: 'bg-red-100 text-red-700',
  paid: 'bg-indigo-100 text-indigo-700',
};

type ClaimAction = 'submit' | 'review' | 'approve' | 'deny' | 'pay';

const VALID_ACTIONS: Record<string, ClaimAction[]> = {
  draft: ['submit'],
  submitted: ['review'],
  under_review: ['approve', 'deny'],
  approved: ['pay'],
  denied: [],
  paid: [],
};

const ACTION_LABELS: Record<ClaimAction, string> = {
  submit: 'Submit',
  review: 'Mark Under Review',
  approve: 'Approve',
  deny: 'Deny',
  pay: 'Mark Paid',
};

export default function ClaimDetailPage() {
  const { claimId } = useParams<{ claimId: string }>();
  const qc = useQueryClient();

  const { data: claim, isLoading } = useQuery({
    queryKey: ['claims', 'detail', claimId],
    queryFn: () => api.get<Claim>(endpoints.claim(claimId!)).then((r) => r.data),
    staleTime: STALE.DETAIL,
    enabled: !!claimId,
  });

  const { mutate: doAction, isPending } = useMutation({
    mutationFn: (action: ClaimAction) =>
      api.post(endpoints.claimAction(claimId!, action), {}, {
        headers: { 'Idempotency-Key': generateIdempotencyKey() },
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['claims', 'detail', claimId] });
      qc.invalidateQueries({ queryKey: ['claims', 'list'] });
    },
  });

  if (isLoading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>;
  if (!claim) return <p className="text-center text-gray-500 py-16">Claim not found.</p>;

  const actions = VALID_ACTIONS[claim.status] ?? [];

  return (
    <div className="flex flex-col gap-6 max-w-4xl">
      <nav className="text-sm text-gray-500">
        <Link to="/insurance/claims" className="hover:underline">Claims</Link>
        <span className="mx-2">/</span>
        <span className="text-gray-900">#{claim.claim_number}</span>
      </nav>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Main */}
        <div className="lg:col-span-2 space-y-6">
          <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div className="flex items-start justify-between">
              <div>
                <h1 className="text-xl font-semibold text-gray-900">Claim #{claim.claim_number}</h1>
                <Link to={`/patients/${claim.patient_id}`} className="text-blue-600 hover:underline text-sm mt-1 block">
                  {claim.patient_name}
                </Link>
              </div>
              <ClaimStatusBadge status={claim.status} />
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
              <div>
                <dt className="text-gray-500">Payer</dt>
                <dd className="font-medium text-gray-900">{claim.payer_name}</dd>
              </div>
              <div>
                <dt className="text-gray-500">Service date</dt>
                <dd className="font-medium text-gray-900">{format(new Date(claim.service_date), 'MMM d, yyyy')}</dd>
              </div>
              {claim.submitted_at && (
                <div>
                  <dt className="text-gray-500">Submitted</dt>
                  <dd className="font-medium text-gray-900">{format(new Date(claim.submitted_at), 'MMM d, yyyy')}</dd>
                </div>
              )}
              <div>
                <dt className="text-gray-500">Billed amount</dt>
                <dd className="font-semibold text-gray-900"><MoneyDisplay money={claim.billed_amount} /></dd>
              </div>
              {claim.allowed_amount && (
                <div>
                  <dt className="text-gray-500">Allowed amount</dt>
                  <dd className="font-medium text-gray-900"><MoneyDisplay money={claim.allowed_amount} /></dd>
                </div>
              )}
              {claim.paid_amount && (
                <div>
                  <dt className="text-gray-500">Paid amount</dt>
                  <dd className="font-medium text-green-700"><MoneyDisplay money={claim.paid_amount} /></dd>
                </div>
              )}
            </dl>

            {claim.status === 'denied' && claim.denial_reason && (
              <div className="mt-4 rounded-lg bg-red-50 border border-red-200 p-3">
                <p className="text-sm font-medium text-red-800">Denial reason</p>
                {claim.denial_code && <p className="text-xs text-red-600 font-mono">{claim.denial_code}</p>}
                <p className="text-sm text-red-700 mt-1">{claim.denial_reason}</p>
              </div>
            )}
          </div>

          {/* Claim lines */}
          <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 className="font-semibold text-gray-900 mb-3">Claim lines</h2>
            <table className="w-full text-sm">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-3 py-2 text-left text-gray-600 font-medium">Code</th>
                  <th className="px-3 py-2 text-left text-gray-600 font-medium">Description</th>
                  <th className="px-3 py-2 text-right text-gray-600 font-medium">Qty</th>
                  <th className="px-3 py-2 text-right text-gray-600 font-medium">Billed</th>
                  <th className="px-3 py-2 text-right text-gray-600 font-medium">Allowed</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {claim.lines.map((line) => (
                  <tr key={line.id}>
                    <td className="px-3 py-2.5">
                      <code className="text-xs font-mono bg-gray-100 px-1.5 py-0.5 rounded">{line.service_code}</code>
                    </td>
                    <td className="px-3 py-2.5 text-gray-700">{line.service_description}</td>
                    <td className="px-3 py-2.5 text-right text-gray-600">{line.quantity}</td>
                    <td className="px-3 py-2.5 text-right font-medium"><MoneyDisplay money={line.billed_amount} /></td>
                    <td className="px-3 py-2.5 text-right text-gray-600">
                      {line.allowed_amount ? <MoneyDisplay money={line.allowed_amount} /> : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Attachments */}
          <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 className="font-semibold text-gray-900 mb-3">Attachments ({claim.attachments.length})</h2>
            {claim.attachments.length === 0 ? (
              <p className="text-sm text-gray-400">No attachments uploaded.</p>
            ) : (
              <ul className="divide-y divide-gray-100">
                {claim.attachments.map((att) => (
                  <li key={att.id} className="flex items-center justify-between py-2">
                    <div>
                      <p className="text-sm font-medium text-gray-900">{att.name}</p>
                      <p className="text-xs text-gray-400">{att.mime_type} · {Math.round(att.size_bytes / 1024)}KB</p>
                    </div>
                    <a href={att.url} target="_blank" rel="noreferrer" className="text-sm text-blue-600 hover:underline">
                      Download
                    </a>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        {/* Actions */}
        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm self-start">
          <h2 className="font-semibold text-gray-900 mb-3">Actions</h2>
          {actions.length === 0 ? (
            <p className="text-sm text-gray-400">No further actions available.</p>
          ) : (
            <div className="flex flex-col gap-2">
              {actions.map((action) => (
                <Button
                  key={action}
                  variant={action === 'deny' ? 'danger' : 'primary'}
                  className="w-full justify-center"
                  isLoading={isPending}
                  onClick={() => doAction(action)}
                >
                  {ACTION_LABELS[action]}
                </Button>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
