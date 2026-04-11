import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { useAuthStore } from '@/store/authStore';
import { STALE } from '@/lib/query/queryClient';

interface Session {
  id: string;
  user_agent?: string;
  ip_address?: string;
  last_active_at: string;
  created_at: string;
  is_current: boolean;
}

export default function SessionsPage() {
  const qc = useQueryClient();
  const { sessionId } = useAuthStore();

  const { data: sessions, isLoading } = useQuery({
    queryKey: ['sessions', 'list'],
    queryFn: () => api.get<Session[]>(endpoints.auth.sessions).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const { mutate: revokeSession, isPending } = useMutation({
    mutationFn: (id: string) => api.delete(endpoints.auth.session(id)),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sessions'] }),
  });

  const { mutate: revokeAll } = useMutation({
    mutationFn: () =>
      Promise.all(
        (sessions ?? [])
          .filter((s) => !s.is_current)
          .map((s) => api.delete(endpoints.auth.session(s.id)))
      ),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['sessions'] }),
  });

  const otherSessions = (sessions ?? []).filter((s) => !s.is_current);

  if (isLoading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>;

  return (
    <div className="flex flex-col gap-6 max-w-2xl">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Active sessions</h1>
        {otherSessions.length > 0 && (
          <Button variant="danger" onClick={() => revokeAll()}>
            Revoke all other sessions
          </Button>
        )}
      </div>

      <div className="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
        {(sessions ?? []).length === 0 && (
          <p className="px-5 py-8 text-center text-sm text-gray-400">No active sessions found.</p>
        )}
        {(sessions ?? []).map((session) => (
          <div key={session.id} className="flex items-center justify-between px-5 py-4">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <p className="text-sm font-medium text-gray-900 truncate">
                  {session.user_agent ?? 'Unknown device'}
                </p>
                {session.is_current && (
                  <Badge variant="green">Current</Badge>
                )}
              </div>
              <p className="text-xs text-gray-400 mt-0.5">
                {session.ip_address ?? 'Unknown IP'} ·{' '}
                Last active: {format(new Date(session.last_active_at), 'MMM d, h:mm a')}
              </p>
            </div>
            {!session.is_current && (
              <Button
                size="sm"
                variant="danger"
                isLoading={isPending}
                onClick={() => revokeSession(session.id)}
              >
                Revoke
              </Button>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
