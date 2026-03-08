<?php

namespace App\Modules\IdentityAccess\Infrastructure\Security\Persistence;

use App\Shared\Infrastructure\Persistence\TenantAwareModel;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $cidr
 * @property string|null $label
 * @property int $position
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class TenantIpAllowlistEntryRecord extends TenantAwareModel
{
    protected $table = 'tenant_ip_allowlist_entries';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
