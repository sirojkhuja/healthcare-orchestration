<?php

namespace App\Modules\Treatment\Application\Data;

final readonly class TreatmentPlanSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $patientId = null,
        public ?string $providerId = null,
        public ?string $plannedFrom = null,
        public ?string $plannedTo = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'status' => $this->status,
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'planned_from' => $this->plannedFrom,
            'planned_to' => $this->plannedTo,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
