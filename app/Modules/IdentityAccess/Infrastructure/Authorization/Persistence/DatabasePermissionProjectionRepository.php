<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization\Persistence;

use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionRepository;
use App\Modules\IdentityAccess\Application\Data\PermissionProjection;
use Illuminate\Database\Query\JoinClause;

final class DatabasePermissionProjectionRepository implements PermissionProjectionRepository
{
    #[\Override]
    public function forUser(string $userId, ?string $tenantId): PermissionProjection
    {
        if (! is_string($tenantId) || $tenantId === '') {
            return new PermissionProjection(
                userId: $userId,
                tenantId: $tenantId,
                permissions: [],
            );
        }

        /** @var list<string> $permissions */
        $permissions = RolePermissionRecord::query()
            ->join('roles', function (JoinClause $join) use ($tenantId): void {
                $join->on('role_permissions.role_id', '=', 'roles.id')
                    ->where('roles.tenant_id', '=', $tenantId);
            })
            ->join('user_role_assignments', function (JoinClause $join) use ($tenantId, $userId): void {
                $join->on('role_permissions.role_id', '=', 'user_role_assignments.role_id')
                    ->where('user_role_assignments.tenant_id', '=', $tenantId)
                    ->where('user_role_assignments.user_id', '=', $userId);
            })
            ->join('tenant_user_memberships', function (JoinClause $join) use ($tenantId, $userId): void {
                $join->on('tenant_user_memberships.user_id', '=', 'user_role_assignments.user_id')
                    ->on('tenant_user_memberships.tenant_id', '=', 'user_role_assignments.tenant_id')
                    ->where('tenant_user_memberships.tenant_id', '=', $tenantId)
                    ->where('tenant_user_memberships.user_id', '=', $userId)
                    ->where('tenant_user_memberships.status', '=', 'active');
            })
            ->distinct()
            ->orderBy('role_permissions.permission')
            ->pluck('role_permissions.permission')
            ->all();

        return new PermissionProjection(
            userId: $userId,
            tenantId: $tenantId,
            permissions: $permissions,
        );
    }
}
