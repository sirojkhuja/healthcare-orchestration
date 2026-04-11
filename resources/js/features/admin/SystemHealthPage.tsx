import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';
import { generateIdempotencyKey } from '@/lib/api/idempotency';
import { STALE } from '@/lib/query/queryClient';

interface HealthResponse { status: 'ok' | 'degraded' | 'down'; checks: Record<string, { status: string; message?: string }> }
interface FeatureFlag { key: string; label: string; description?: string; enabled: boolean }
interface RateLimit { group: string; requests_per_minute: number; burst: number }
interface Job { id: string; name: string; status: string; attempts: number; last_error?: string; payload?: unknown }

export default function SystemHealthPage() {
  const qc = useQueryClient();
  const [confirmFlush, setConfirmFlush] = useState(false);
  const [mutationError, setMutationError] = useState<Error | null>(null);

  const { data: health, dataUpdatedAt } = useQuery({
    queryKey: ['health'],
    queryFn: () => api.get<HealthResponse>(endpoints.health).then((r) => r.data),
    staleTime: STALE.REALTIME,
    refetchInterval: 30_000,
  });

  const { data: flags } = useQuery({
    queryKey: ['feature-flags'],
    queryFn: () => api.get<FeatureFlag[]>(endpoints.adminFeatureFlags).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const { data: rateLimits } = useQuery({
    queryKey: ['rate-limits'],
    queryFn: () => api.get<RateLimit[]>(endpoints.adminRateLimits).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const { data: jobs } = useQuery({
    queryKey: ['admin-jobs'],
    queryFn: () => api.get<{ data: Job[] }>(endpoints.adminJobs).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const { mutate: toggleFlag } = useMutation({
    mutationFn: ({ key, enabled }: { key: string; enabled: boolean }) =>
      api.put(endpoints.adminFeatureFlag(key), { enabled }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['feature-flags'] }),
    onError: setMutationError,
  });

  const { mutate: flushCache, isPending: flushing } = useMutation({
    mutationFn: () =>
      api.post(endpoints.adminCache('flush'), {}, { headers: { 'Idempotency-Key': generateIdempotencyKey() } }),
    onSuccess: () => { qc.clear(); setConfirmFlush(false); },
    onError: setMutationError,
  });

  const { mutate: retryJob } = useMutation({
    mutationFn: (jobId: string) =>
      api.post(endpoints.adminJobAction(jobId, 'retry'), {}, { headers: { 'Idempotency-Key': generateIdempotencyKey() } }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin-jobs'] }),
    onError: setMutationError,
  });

  const healthStatus = health?.status ?? 'unknown';

  return (
    <div className="flex flex-col gap-8">
      <h1 className="text-2xl font-semibold text-gray-900">System Health</h1>

      {mutationError && <ApiErrorAlert error={mutationError} />}

      {/* Health probes */}
      <section>
        <h2 className="mb-3 text-sm font-semibold text-gray-500 uppercase tracking-wide">Health probes</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
              <span className="font-medium text-gray-900">Overall</span>
              <Badge variant={healthStatus === 'ok' ? 'green' : 'red'} className="capitalize">
                {healthStatus}
              </Badge>
            </div>
            {dataUpdatedAt > 0 && (
              <p className="mt-2 text-xs text-gray-400">
                Last checked: {new Date(dataUpdatedAt).toLocaleTimeString()}
              </p>
            )}
          </div>
          {Object.entries(health?.checks ?? {}).map(([name, check]) => (
            <div key={name} className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="font-medium text-gray-900 capitalize">{name}</span>
                <Badge variant={check.status === 'ok' ? 'green' : 'red'} className="capitalize">
                  {check.status}
                </Badge>
              </div>
              {check.message && <p className="mt-1 text-xs text-gray-500">{check.message}</p>}
            </div>
          ))}
        </div>
      </section>

      {/* Feature flags */}
      <section>
        <h2 className="mb-3 text-sm font-semibold text-gray-500 uppercase tracking-wide">Feature flags</h2>
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
          {(flags ?? []).length === 0 && (
            <p className="px-5 py-4 text-sm text-gray-400">No feature flags configured.</p>
          )}
          {(flags ?? []).map((flag) => (
            <div key={flag.key} className="flex items-center justify-between px-5 py-3">
              <div>
                <p className="text-sm font-medium text-gray-900">{flag.label}</p>
                {flag.description && <p className="text-xs text-gray-500">{flag.description}</p>}
              </div>
              <button
                onClick={() => toggleFlag({ key: flag.key, enabled: !flag.enabled })}
                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${flag.enabled ? 'bg-blue-600' : 'bg-gray-200'}`}
              >
                <span
                  className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${flag.enabled ? 'translate-x-6' : 'translate-x-1'}`}
                />
              </button>
            </div>
          ))}
        </div>
      </section>

      {/* Rate limits */}
      <section>
        <h2 className="mb-3 text-sm font-semibold text-gray-500 uppercase tracking-wide">Rate limits</h2>
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-5 py-3 text-left font-medium text-gray-600">Endpoint group</th>
                <th className="px-5 py-3 text-right font-medium text-gray-600">Requests/min</th>
                <th className="px-5 py-3 text-right font-medium text-gray-600">Burst</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {(rateLimits ?? []).map((rl) => (
                <tr key={rl.group}>
                  <td className="px-5 py-3 font-medium text-gray-900 capitalize">{rl.group}</td>
                  <td className="px-5 py-3 text-right text-gray-700">{rl.requests_per_minute}</td>
                  <td className="px-5 py-3 text-right text-gray-700">{rl.burst}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {/* Cache operations */}
      <section>
        <h2 className="mb-3 text-sm font-semibold text-gray-500 uppercase tracking-wide">Cache</h2>
        <div className="flex gap-3">
          <Button variant="danger" onClick={() => setConfirmFlush(true)}>Flush cache</Button>
          <Button variant="secondary" onClick={() =>
            api.post(endpoints.adminCache('rebuild'), {}, { headers: { 'Idempotency-Key': generateIdempotencyKey() } })
              .then(() => qc.invalidateQueries())
          }>
            Rebuild cache
          </Button>
        </div>
      </section>

      {/* Job queue */}
      {(jobs?.data ?? []).length > 0 && (
        <section>
          <h2 className="mb-3 text-sm font-semibold text-gray-500 uppercase tracking-wide">Failed jobs</h2>
          <div className="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
            {jobs!.data.filter((j) => j.status === 'failed').map((job) => (
              <div key={job.id} className="flex items-center justify-between px-5 py-3">
                <div>
                  <p className="text-sm font-medium text-gray-900">{job.name}</p>
                  {job.last_error && <p className="text-xs text-red-500 mt-0.5 truncate max-w-xs">{job.last_error}</p>}
                  <p className="text-xs text-gray-400">Attempts: {job.attempts}</p>
                </div>
                <Button size="sm" onClick={() => retryJob(job.id)}>Retry</Button>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Flush confirm modal */}
      <Modal isOpen={confirmFlush} onClose={() => setConfirmFlush(false)} title="Flush cache">
        <div className="flex flex-col gap-4">
          <p className="text-sm text-gray-600">
            This will clear all Redis cache entries for this tenant. Active sessions will not be affected,
            but next requests may be slower as cache is rebuilt.
          </p>
          <div className="flex justify-end gap-3">
            <Button variant="secondary" onClick={() => setConfirmFlush(false)}>Cancel</Button>
            <Button variant="danger" isLoading={flushing} onClick={() => flushCache()}>Flush cache</Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
