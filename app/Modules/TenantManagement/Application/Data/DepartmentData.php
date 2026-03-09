<?php

namespace App\Modules\TenantManagement\Application\Data;

use Carbon\CarbonImmutable;

final readonly class DepartmentData
{
    public function __construct(
        public string $departmentId,
        public string $clinicId,
        public string $code,
        public string $name,
        public ?string $description,
        public ?string $phoneExtension,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->departmentId,
            'clinic_id' => $this->clinicId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'phone_extension' => $this->phoneExtension,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
