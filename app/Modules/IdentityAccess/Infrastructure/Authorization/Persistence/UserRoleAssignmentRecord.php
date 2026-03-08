<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization\Persistence;

use App\Shared\Infrastructure\Persistence\TenantAwareModel;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $role_id
 */
final class UserRoleAssignmentRecord extends TenantAwareModel
{
    protected $table = 'user_role_assignments';

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
