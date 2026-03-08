<?php

namespace Tests\Fixtures\IdentityAccess;

use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionRepository;
use App\Modules\IdentityAccess\Application\Data\PermissionProjection;

final class FakePermissionProjectionRepository implements PermissionProjectionRepository
{
    /** @var array<string, list<string>> */
    private array $permissionsByScope = [];

    public int $loadCount = 0;

    /**
     * @param  list<string>  $permissions
     */
    public function setPermissions(string $userId, ?string $tenantId, array $permissions): void
    {
        $this->permissionsByScope[$this->scopeKey($userId, $tenantId)] = $permissions;
    }

    #[\Override]
    public function forUser(string $userId, ?string $tenantId): PermissionProjection
    {
        $this->loadCount++;

        return new PermissionProjection(
            userId: $userId,
            tenantId: $tenantId,
            permissions: $this->permissionsByScope[$this->scopeKey($userId, $tenantId)] ?? [],
        );
    }

    private function scopeKey(string $userId, ?string $tenantId): string
    {
        return ($tenantId ?? 'global').":{$userId}";
    }
}
