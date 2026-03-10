<?php

namespace App\Modules\Treatment\Application\Data;

use Carbon\CarbonImmutable;

final readonly class EncounterProcedureData
{
    public function __construct(
        public string $procedureId,
        public string $tenantId,
        public string $encounterId,
        public ?string $treatmentItemId,
        public ?string $code,
        public string $displayName,
        public ?CarbonImmutable $performedAt,
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
            'id' => $this->procedureId,
            'tenant_id' => $this->tenantId,
            'encounter_id' => $this->encounterId,
            'treatment_item_id' => $this->treatmentItemId,
            'code' => $this->code,
            'display_name' => $this->displayName,
            'performed_at' => $this->performedAt?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
