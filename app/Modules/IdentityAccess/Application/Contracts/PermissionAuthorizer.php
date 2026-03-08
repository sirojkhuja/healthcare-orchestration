<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

interface PermissionAuthorizer
{
    public function allows(string $userId, ?string $tenantId, string $permission): bool;

    public function forget(string $userId, ?string $tenantId): void;
}
