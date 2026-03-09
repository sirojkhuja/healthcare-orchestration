<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientExternalReferenceData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $referenceId,
        public string $patientId,
        public string $integrationKey,
        public string $externalId,
        public string $externalType,
        public ?string $displayName,
        public array $metadata,
        public CarbonImmutable $linkedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->referenceId,
            'patient_id' => $this->patientId,
            'integration_key' => $this->integrationKey,
            'external_id' => $this->externalId,
            'external_type' => $this->externalType,
            'display_name' => $this->displayName,
            'metadata' => $this->metadata,
            'linked_at' => $this->linkedAt->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
