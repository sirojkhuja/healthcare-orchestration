<?php

namespace App\Modules\IdentityAccess\Application\Queries;

final readonly class ListUsersQuery
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
    ) {}
}
