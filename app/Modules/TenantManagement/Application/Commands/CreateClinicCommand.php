<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class CreateClinicCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
