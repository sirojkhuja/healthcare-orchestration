<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization;

use App\Modules\IdentityAccess\Application\Contracts\PermissionCatalog;
use App\Modules\IdentityAccess\Application\Data\PermissionDefinitionData;
use App\Modules\IdentityAccess\Application\Data\PermissionGroupData;

final class ConfigPermissionCatalog implements PermissionCatalog
{
    #[\Override]
    public function all(): array
    {
        $definitions = [];

        foreach ($this->catalogConfig() as $groupKey => $group) {
            foreach ($group['permissions'] as $permissionName => $description) {
                $definitions[] = new PermissionDefinitionData(
                    name: $permissionName,
                    groupKey: $groupKey,
                    groupName: $group['name'],
                    groupDescription: $group['description'],
                    description: $description,
                );
            }
        }

        usort($definitions, static fn (PermissionDefinitionData $left, PermissionDefinitionData $right): int => strcmp($left->name, $right->name));

        return $definitions;
    }

    #[\Override]
    public function definitions(array $permissionNames): array
    {
        $requested = array_fill_keys($permissionNames, true);

        return array_values(array_filter(
            $this->all(),
            static fn (PermissionDefinitionData $definition): bool => array_key_exists($definition->name, $requested),
        ));
    }

    #[\Override]
    public function exists(string $permissionName): bool
    {
        foreach ($this->catalogConfig() as $group) {
            if (array_key_exists($permissionName, $group['permissions'])) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function groups(): array
    {
        $groups = [];

        foreach ($this->catalogConfig() as $groupKey => $group) {
            $permissions = [];

            foreach ($group['permissions'] as $permissionName => $description) {
                $permissions[] = new PermissionDefinitionData(
                    name: $permissionName,
                    groupKey: $groupKey,
                    groupName: $group['name'],
                    groupDescription: $group['description'],
                    description: $description,
                );
            }

            $groups[] = new PermissionGroupData(
                key: $groupKey,
                name: $group['name'],
                description: $group['description'],
                permissions: $permissions,
            );
        }

        return $groups;
    }

    /**
     * @return array<string, array{name: string, description: string, permissions: array<string, string>}>
     */
    private function catalogConfig(): array
    {
        $config = config('rbac.groups', []);

        if (! is_array($config)) {
            return [];
        }

        $groups = [];

        foreach ($config as $groupKey => $group) {
            if (! is_string($groupKey) || ! is_array($group)) {
                continue;
            }

            $name = $group['name'] ?? null;
            $description = $group['description'] ?? null;
            $permissions = $group['permissions'] ?? null;

            if (! is_string($name) || ! is_string($description) || ! is_array($permissions)) {
                continue;
            }

            $normalizedPermissions = [];
            /** @var array<array-key, mixed> $permissionEntries */
            $permissionEntries = $permissions;

            /** @psalm-suppress MixedAssignment */
            foreach ($permissionEntries as $permissionName => $permissionDescription) {
                if (! is_string($permissionName) || ! is_string($permissionDescription)) {
                    continue;
                }

                $normalizedPermissions[$permissionName] = $permissionDescription;
            }

            $groups[$groupKey] = [
                'name' => $name,
                'description' => $description,
                'permissions' => $normalizedPermissions,
            ];
        }

        return $groups;
    }
}
