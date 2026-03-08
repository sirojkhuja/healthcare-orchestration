<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class ListTenantsQuery
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
    ) {}
}
