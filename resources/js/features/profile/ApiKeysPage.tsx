import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { STALE } from '@/lib/query/queryClient';
import type { PaginatedResponse } from '@/types/common';

interface ApiKey {
  id: string;
  name: string;
  key_prefix: string;
  created_at: string;
  last_used_at?: string;
  expires_at?: string;
  scopes: string[];
}

interface CreateApiKeyResponse {
  id: string;
  name: string;
  key: string; // plaintext, shown only once
  key_prefix: string;
}

const createSchema = z.object({
  name: z.string().min(1, 'Name is required').max(100),
  expires_at: z.string().optional(),
});
type CreateForm = z.infer<typeof createSchema>;

export default function ApiKeysPage() {
  const qc = useQueryClient();
  const [createOpen, setCreateOpen] = useState(false);
  const [newKey, setNewKey] = useState<CreateApiKeyResponse | null>(null);
  const [copied, setCopied] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ['api-keys', 'list'],
    queryFn: () =>
      api.get<PaginatedResponse<ApiKey>>(endpoints.auth.apiKeys).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const { register, handleSubmit, reset, formState: { errors } } = useForm<CreateForm>({
    resolver: zodResolver(createSchema),
  });

  const { mutate: createKey, isPending: creating } = useMutation({
    mutationFn: (payload: CreateForm) =>
      api.post<CreateApiKeyResponse>(endpoints.auth.apiKeys, payload).then((r) => r.data),
    onSuccess: (data) => {
      qc.invalidateQueries({ queryKey: ['api-keys'] });
      setNewKey(data);
      setCreateOpen(false);
      reset();
    },
  });

  const { mutate: revokeKey } = useMutation({
    mutationFn: (id: string) => api.delete(endpoints.auth.apiKey(id)),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['api-keys'] }),
  });

  const copyKey = async () => {
    if (newKey) {
      await navigator.clipboard.writeText(newKey.key);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  return (
    <div className="flex flex-col gap-6 max-w-2xl">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">API Keys</h1>
        <Button onClick={() => setCreateOpen(true)}>+ Create API key</Button>
      </div>

      <div className="rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
        {isLoading && <div className="flex justify-center py-8"><div className="animate-spin h-5 w-5 border-2 border-blue-600 border-t-transparent rounded-full" /></div>}
        {!isLoading && (data?.data ?? []).length === 0 && (
          <p className="px-5 py-8 text-center text-sm text-gray-400">No API keys. Create one to get started.</p>
        )}
        {(data?.data ?? []).map((key) => (
          <div key={key.id} className="flex items-center justify-between px-5 py-4">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <p className="text-sm font-medium text-gray-900">{key.name}</p>
                {key.scopes.map((scope) => (
                  <Badge key={scope} variant="gray" className="text-xs">{scope}</Badge>
                ))}
              </div>
              <p className="text-xs font-mono text-gray-400 mt-0.5">
                {key.key_prefix}… · Created {format(new Date(key.created_at), 'MMM d, yyyy')}
                {key.last_used_at && ` · Last used ${format(new Date(key.last_used_at), 'MMM d')}`}
                {key.expires_at && ` · Expires ${format(new Date(key.expires_at), 'MMM d, yyyy')}`}
              </p>
            </div>
            <Button size="sm" variant="danger" onClick={() => revokeKey(key.id)}>Revoke</Button>
          </div>
        ))}
      </div>

      {/* Create modal */}
      <Modal isOpen={createOpen} onClose={() => { setCreateOpen(false); reset(); }} title="Create API key">
        <form onSubmit={handleSubmit((d) => createKey(d))} className="flex flex-col gap-4">
          <Input label="Key name" placeholder="e.g. Production integration" error={errors.name?.message} {...register('name')} />
          <Input label="Expiry date (optional)" type="date" {...register('expires_at')} />
          <div className="flex justify-end gap-3">
            <Button variant="secondary" type="button" onClick={() => { setCreateOpen(false); reset(); }}>Cancel</Button>
            <Button type="submit" isLoading={creating}>Create</Button>
          </div>
        </form>
      </Modal>

      {/* One-time key display modal */}
      <Modal
        isOpen={!!newKey}
        onClose={() => { }}
        title="Your new API key"
      >
        <div className="flex flex-col gap-4">
          <div className="rounded-lg bg-amber-50 border border-amber-200 p-3">
            <p className="text-sm font-medium text-amber-800">Save this key — it will never be shown again</p>
          </div>
          <div className="relative">
            <code className="block w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-mono break-all text-gray-900 pr-20">
              {newKey?.key}
            </code>
            <button
              onClick={copyKey}
              className="absolute right-2 top-2 rounded px-2 py-1 text-xs font-medium bg-white border border-gray-200 hover:bg-gray-50"
            >
              {copied ? '✓ Copied' : 'Copy'}
            </button>
          </div>
          <Button
            className="w-full justify-center"
            onClick={() => setNewKey(null)}
          >
            I've saved my key
          </Button>
        </div>
      </Modal>
    </div>
  );
}
