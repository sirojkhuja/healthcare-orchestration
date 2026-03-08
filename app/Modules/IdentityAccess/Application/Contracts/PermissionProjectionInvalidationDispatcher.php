<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

interface PermissionProjectionInvalidationDispatcher
{
    public function invalidate(string $userId, ?string $tenantId): void;

    /**
     * @param  list<string>  $userIds
     */
    public function invalidateMany(array $userIds, ?string $tenantId): void;
}
