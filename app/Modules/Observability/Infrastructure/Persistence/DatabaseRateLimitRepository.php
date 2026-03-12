<?php

namespace App\Modules\Observability\Infrastructure\Persistence;

use App\Modules\Observability\Application\Contracts\RateLimitRepository;
use Illuminate\Support\Str;

final class DatabaseRateLimitRepository implements RateLimitRepository
{
    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, RateLimitOverrideRecord> $records */
        $records = RateLimitOverrideRecord::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('bucket_key')
            ->get();

        $items = [];

        foreach ($records as $record) {
            $items[$record->bucket_key] = [
                'requests_per_minute' => $record->requests_per_minute,
                'burst' => $record->burst,
                'updated_at' => $record->updated_at,
            ];
        }

        return $items;
    }

    #[\Override]
    public function saveMany(string $tenantId, array $limits): void
    {
        foreach ($limits as $bucketKey => $limit) {
            RateLimitOverrideRecord::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'bucket_key' => $bucketKey,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'requests_per_minute' => $limit['requests_per_minute'],
                    'burst' => $limit['burst'],
                ],
            );
        }
    }
}
