<?php

namespace App\Modules\IdentityAccess\Application\Queries;

final readonly class ListUserRolesQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
