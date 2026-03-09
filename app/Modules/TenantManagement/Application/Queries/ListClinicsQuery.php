<?php

namespace App\Modules\TenantManagement\Application\Queries;

final readonly class ListClinicsQuery
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
    ) {}
}
