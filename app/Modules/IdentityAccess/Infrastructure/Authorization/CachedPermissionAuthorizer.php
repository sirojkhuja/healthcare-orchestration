<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization;

use App\Modules\IdentityAccess\Application\Contracts\PermissionAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionRepository;
use App\Modules\IdentityAccess\Application\Data\PermissionProjection;
use App\Shared\Application\Contracts\TenantCache;

final class CachedPermissionAuthorizer implements PermissionAuthorizer
{
    public function __construct(
        private readonly PermissionProjectionRepository $permissionProjectionRepository,
        private readonly TenantCache $tenantCache,
    ) {}

    #[\Override]
    public function allows(string $userId, ?string $tenantId, string $permission): bool
    {
        return $this->projection($userId, $tenantId)->allows($permission);
    }

    #[\Override]
    public function forget(string $userId, ?string $tenantId): void
    {
        $this->tenantCache->forget('permissions', [$userId], $tenantId);
    }

    private function projection(string $userId, ?string $tenantId): PermissionProjection
    {
        /** @var PermissionProjection $projection */
        $projection = $this->tenantCache->remember(
            'permissions',
            [$userId],
            $tenantId,
            null,
            fn () => $this->permissionProjectionRepository->forUser($userId, $tenantId),
        );

        return $projection;
    }
}
