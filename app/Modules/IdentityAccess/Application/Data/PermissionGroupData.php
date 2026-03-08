<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class PermissionGroupData
{
    /**
     * @param  list<PermissionDefinitionData>  $permissions
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public array $permissions,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     permissions: list<array{
     *         name: string,
     *         group_key: string,
     *         group_name: string,
     *         group_description: string,
     *         description: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => array_map(
                static fn (PermissionDefinitionData $permission): array => $permission->toArray(),
                $this->permissions,
            ),
        ];
    }
}
