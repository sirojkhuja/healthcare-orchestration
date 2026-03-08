<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\PermissionProjection;

interface PermissionProjectionRepository
{
    public function forUser(string $userId, ?string $tenantId): PermissionProjection;
}
