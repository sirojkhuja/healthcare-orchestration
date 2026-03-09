<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class ListRoomsQuery
{
    public function __construct(
        public string $clinicId,
    ) {}
}
