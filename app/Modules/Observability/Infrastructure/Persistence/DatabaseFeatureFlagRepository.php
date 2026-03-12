<?php

namespace App\Modules\Observability\Infrastructure\Persistence;

use App\Modules\Observability\Application\Contracts\FeatureFlagRepository;
use Illuminate\Support\Str;

final class DatabaseFeatureFlagRepository implements FeatureFlagRepository
{
    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, FeatureFlagOverrideRecord> $records */
        $records = FeatureFlagOverrideRecord::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('flag_key')
            ->get();

        $items = [];

        foreach ($records as $record) {
            $items[$record->flag_key] = [
                'enabled' => $record->enabled,
                'updated_at' => $record->updated_at,
            ];
        }

        return $items;
    }

    #[\Override]
    public function saveMany(string $tenantId, array $flags): void
    {
        foreach ($flags as $key => $enabled) {
            FeatureFlagOverrideRecord::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'flag_key' => $key,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'enabled' => $enabled,
                ],
            );
        }
    }
}
