<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class ListDistrictsQuery
{
    public function __construct(
        public string $cityCode,
    ) {}
}
