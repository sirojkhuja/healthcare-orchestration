<?php

namespace App\Modules\IdentityAccess\Application\Queries;

final readonly class GetProfileQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
