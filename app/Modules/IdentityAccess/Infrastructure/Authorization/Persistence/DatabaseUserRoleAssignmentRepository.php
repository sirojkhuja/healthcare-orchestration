<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization\Persistence;

use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use App\Modules\IdentityAccess\Application\Data\RoleData;
use Illuminate\Database\Query\JoinClause;

final class DatabaseUserRoleAssignmentRepository implements UserRoleAssignmentRepository
{
    #[\Override]
    public function assignedUserIdsForRole(string $roleId, string $tenantId): array
    {
        /** @var list<string> $userIds */
        $userIds = UserRoleAssignmentRecord::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('role_id', $roleId)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->all();

        return $userIds;
    }

    #[\Override]
    public function listRolesForUser(string $userId, string $tenantId): array
    {
        /** @var list<RoleRecord> $records */
        $records = RoleRecord::query()
            ->withoutGlobalScopes()
            ->join('user_role_assignments', function (JoinClause $join) use ($tenantId, $userId): void {
                $join->on('roles.id', '=', 'user_role_assignments.role_id')
                    ->where('user_role_assignments.tenant_id', '=', $tenantId)
                    ->where('user_role_assignments.user_id', '=', $userId);
            })
            ->where('roles.tenant_id', $tenantId)
            ->select('roles.*')
            ->orderBy('roles.name')
            ->get()
            ->all();

        return array_map(
            static fn (RoleRecord $record): RoleData => new RoleData(
                roleId: $record->id,
                name: $record->name,
                description: $record->description,
                createdAt: $record->created_at,
                updatedAt: $record->updated_at,
            ),
            $records,
        );
    }

    #[\Override]
    public function replaceRolesForUser(string $userId, string $tenantId, array $roleIds): void
    {
        UserRoleAssignmentRecord::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();

        foreach (array_values(array_unique($roleIds)) as $roleId) {
            UserRoleAssignmentRecord::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }
}
