<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class ListCitiesQuery
{
    public function __construct(
        public ?string $query = null,
    ) {}
}
