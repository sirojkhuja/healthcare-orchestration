<?php

namespace App\Modules\IdentityAccess\Infrastructure\Security\Persistence;

use App\Modules\IdentityAccess\Application\Contracts\TenantIpAllowlistRepository;
use App\Modules\IdentityAccess\Application\Data\TenantIpAllowlistEntryData;
use App\Modules\IdentityAccess\Infrastructure\Security\CidrMatcher;

final class DatabaseTenantIpAllowlistRepository implements TenantIpAllowlistRepository
{
    public function __construct(
        private readonly CidrMatcher $cidrMatcher,
    ) {}

    #[\Override]
    public function allows(string $tenantId, ?string $ipAddress): bool
    {
        $entries = $this->listForTenant($tenantId);

        if ($entries === []) {
            return true;
        }

        if (! is_string($ipAddress) || $ipAddress === '') {
            return false;
        }

        foreach ($entries as $entry) {
            if ($this->cidrMatcher->matches($ipAddress, $entry->cidr)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var list<TenantIpAllowlistEntryRecord> $records */
        $records = TenantIpAllowlistEntryRecord::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $records);
    }

    #[\Override]
    public function replaceForTenant(string $tenantId, array $entries): array
    {
        TenantIpAllowlistEntryRecord::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->delete();

        foreach ($entries as $index => $entry) {
            TenantIpAllowlistEntryRecord::query()->create([
                'tenant_id' => $tenantId,
                'cidr' => $entry['cidr'],
                'label' => $entry['label'],
                'position' => $index,
            ]);
        }

        return $this->listForTenant($tenantId);
    }

    private function toData(TenantIpAllowlistEntryRecord $record): TenantIpAllowlistEntryData
    {
        return new TenantIpAllowlistEntryData(
            entryId: $record->id,
            cidr: $record->cidr,
            label: $record->label,
            createdAt: $record->created_at,
        );
    }
}
