<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class UserPermissionSetData
{
    /**
     * @param  list<PermissionDefinitionData>  $permissions
     * @param  list<RoleData>  $roles
     */
    public function __construct(
        public string $userId,
        public string $tenantId,
        public array $roles,
        public array $permissions,
    ) {}

    /**
     * @return array{
     *     user_id: string,
     *     tenant_id: string,
     *     roles: list<array{id: string, name: string, description: string|null, created_at: string, updated_at: string}>,
     *     permissions: list<array{name: string, group_key: string, group_name: string, group_description: string, description: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'roles' => array_map(
                static fn (RoleData $role): array => $role->toArray(),
                $this->roles,
            ),
            'permissions' => array_map(
                static fn (PermissionDefinitionData $permission): array => $permission->toArray(),
                $this->permissions,
            ),
        ];
    }
}
