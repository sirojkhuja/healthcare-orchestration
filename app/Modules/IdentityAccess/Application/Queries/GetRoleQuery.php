<?php

namespace App\Modules\IdentityAccess\Application\Queries;

final readonly class GetRoleQuery
{
    public function __construct(
        public string $roleId,
    ) {}
}
