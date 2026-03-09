<?php

namespace App\Modules\TenantManagement\Application\Data;

use Carbon\CarbonImmutable;

final readonly class RoomData
{
    public function __construct(
        public string $roomId,
        public string $clinicId,
        public ?string $departmentId,
        public string $code,
        public string $name,
        public string $type,
        public ?string $floor,
        public int $capacity,
        public ?string $notes,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->roomId,
            'clinic_id' => $this->clinicId,
            'department_id' => $this->departmentId,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'floor' => $this->floor,
            'capacity' => $this->capacity,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
