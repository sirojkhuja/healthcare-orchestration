<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class UpdateClinicCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $clinicId,
        public array $attributes,
    ) {}
}
