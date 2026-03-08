<?php

namespace App\Modules\IdentityAccess\Application\Queries;

final readonly class ListRolePermissionsQuery
{
    public function __construct(
        public string $roleId,
    ) {}
}
