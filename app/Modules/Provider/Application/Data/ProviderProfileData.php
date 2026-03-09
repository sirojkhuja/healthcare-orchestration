<?php

namespace App\Modules\Provider\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderProfileData
{
    /**
     * @param  list<string>  $languages
     */
    public function __construct(
        public string $providerId,
        public string $tenantId,
        public ?string $professionalTitle,
        public ?string $bio,
        public ?int $yearsOfExperience,
        public ?string $departmentId,
        public ?string $roomId,
        public bool $isAcceptingNewPatients,
        public array $languages,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'tenant_id' => $this->tenantId,
            'professional_title' => $this->professionalTitle,
            'bio' => $this->bio,
            'years_of_experience' => $this->yearsOfExperience,
            'department_id' => $this->departmentId,
            'room_id' => $this->roomId,
            'is_accepting_new_patients' => $this->isAcceptingNewPatients,
            'languages' => $this->languages,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
