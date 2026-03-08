<?php

namespace App\Modules\IdentityAccess\Application\Queries;

final readonly class GetRbacAuditQuery
{
    public function __construct(
        public int $limit = 50,
    ) {}
}
