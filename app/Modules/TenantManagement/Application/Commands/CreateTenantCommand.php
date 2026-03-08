<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class CreateTenantCommand
{
    public function __construct(
        public string $name,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
    ) {}
}
