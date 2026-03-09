<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class SearchLocationsQuery
{
    public function __construct(
        public string $query,
    ) {}
}
