<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization;

use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionRepository;
use App\Modules\IdentityAccess\Application\Data\PermissionProjection;

final class NullPermissionProjectionRepository implements PermissionProjectionRepository
{
    #[\Override]
    public function forUser(string $userId, ?string $tenantId): PermissionProjection
    {
        return new PermissionProjection(
            userId: $userId,
            tenantId: $tenantId,
            permissions: [],
        );
    }
}
