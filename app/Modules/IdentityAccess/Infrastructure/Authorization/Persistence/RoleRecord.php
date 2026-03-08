<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization\Persistence;

use App\Shared\Infrastructure\Persistence\TenantAwareModel;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class RoleRecord extends TenantAwareModel
{
    protected $table = 'roles';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
