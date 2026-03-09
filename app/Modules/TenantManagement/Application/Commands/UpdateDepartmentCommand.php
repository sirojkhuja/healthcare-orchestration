<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class UpdateDepartmentCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $clinicId,
        public string $departmentId,
        public array $attributes,
    ) {}
}
