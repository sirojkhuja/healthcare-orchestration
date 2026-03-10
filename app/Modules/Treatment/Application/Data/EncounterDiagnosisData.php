<?php

namespace App\Modules\Treatment\Application\Data;

use Carbon\CarbonImmutable;

final readonly class EncounterDiagnosisData
{
    public function __construct(
        public string $diagnosisId,
        public string $tenantId,
        public string $encounterId,
        public ?string $code,
        public string $displayName,
        public string $diagnosisType,
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
            'id' => $this->diagnosisId,
            'tenant_id' => $this->tenantId,
            'encounter_id' => $this->encounterId,
            'code' => $this->code,
            'display_name' => $this->displayName,
            'diagnosis_type' => $this->diagnosisType,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
