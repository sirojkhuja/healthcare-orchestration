<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization\Persistence;

use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Data\RoleData;

final class DatabaseRoleRepository implements RoleRepository
{
    #[\Override]
    public function create(string $tenantId, string $name, ?string $description): RoleData
    {
        $record = RoleRecord::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => $description,
        ]);

        return $this->toData($record);
    }

    #[\Override]
    public function deleteInTenant(string $roleId, string $tenantId): bool
    {
        return RoleRecord::query()
            ->withoutGlobalScopes()
            ->whereKey($roleId)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $roleId, string $tenantId): ?RoleData
    {
        $record = RoleRecord::query()
            ->withoutGlobalScopes()
            ->whereKey($roleId)
            ->where('tenant_id', $tenantId)
            ->first();

        return $record instanceof RoleRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function listPermissionNames(string $roleId, string $tenantId): array
    {
        if ($this->findInTenant($roleId, $tenantId) === null) {
            return [];
        }

        /** @var list<string> $permissions */
        $permissions = RolePermissionRecord::query()
            ->where('role_id', $roleId)
            ->orderBy('permission')
            ->pluck('permission')
            ->all();

        return $permissions;
    }

    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var list<RoleRecord> $records */
        $records = RoleRecord::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->all();

        return array_map($this->toData(...), $records);
    }

    #[\Override]
    public function nameExists(string $tenantId, string $name, ?string $ignoreRoleId = null): bool
    {
        $query = RoleRecord::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if (is_string($ignoreRoleId) && $ignoreRoleId !== '') {
            $query->where('id', '!=', $ignoreRoleId);
        }

        return $query->exists();
    }

    #[\Override]
    public function replacePermissions(string $roleId, string $tenantId, array $permissionNames): void
    {
        if ($this->findInTenant($roleId, $tenantId) === null) {
            return;
        }

        RolePermissionRecord::query()->where('role_id', $roleId)->delete();

        foreach (array_values(array_unique($permissionNames)) as $permissionName) {
            RolePermissionRecord::query()->create([
                'role_id' => $roleId,
                'permission' => $permissionName,
            ]);
        }
    }

    #[\Override]
    public function updateInTenant(string $roleId, string $tenantId, string $name, ?string $description): ?RoleData
    {
        $updated = RoleRecord::query()
            ->withoutGlobalScopes()
            ->whereKey($roleId)
            ->where('tenant_id', $tenantId)
            ->update([
                'name' => $name,
                'description' => $description,
            ]);

        if ($updated < 1) {
            return null;
        }

        return $this->findInTenant($roleId, $tenantId);
    }

    private function toData(RoleRecord $record): RoleData
    {
        return new RoleData(
            roleId: $record->id,
            name: $record->name,
            description: $record->description,
            createdAt: $record->created_at,
            updatedAt: $record->updated_at,
        );
    }
}
