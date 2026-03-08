<?php

namespace App\Modules\IdentityAccess\Application\Queries;

final readonly class GetUserPermissionsQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
