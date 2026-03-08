<?php

namespace App\Modules\IdentityAccess\Application\Queries;

final readonly class GetUserQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
