<?php

namespace App\Modules\Insurance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientInsurancePolicyData
{
    public function __construct(
        public string $policyId,
        public string $patientId,
        public string $insuranceCode,
        public string $policyNumber,
        public ?string $memberNumber,
        public ?string $groupNumber,
        public ?string $planName,
        public ?CarbonImmutable $effectiveFrom,
        public ?CarbonImmutable $effectiveTo,
        public bool $isPrimary,
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
            'id' => $this->policyId,
            'patient_id' => $this->patientId,
            'insurance_code' => $this->insuranceCode,
            'policy_number' => $this->policyNumber,
            'member_number' => $this->memberNumber,
            'group_number' => $this->groupNumber,
            'plan_name' => $this->planName,
            'effective_from' => $this->effectiveFrom?->toDateString(),
            'effective_to' => $this->effectiveTo?->toDateString(),
            'is_primary' => $this->isPrimary,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
