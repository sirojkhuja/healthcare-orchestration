<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class UpdateTenantCommand
{
    public function __construct(
        public string $tenantId,
        public bool $nameProvided = false,
        public ?string $name = null,
        public bool $contactEmailProvided = false,
        public ?string $contactEmail = null,
        public bool $contactPhoneProvided = false,
        public ?string $contactPhone = null,
    ) {}
}
