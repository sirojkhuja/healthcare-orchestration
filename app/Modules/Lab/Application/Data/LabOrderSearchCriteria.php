<?php

namespace App\Modules\Lab\Application\Data;

final readonly class LabOrderSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $patientId = null,
        public ?string $providerId = null,
        public ?string $encounterId = null,
        public ?string $labTestId = null,
        public ?string $labProviderKey = null,
        public ?string $orderedFrom = null,
        public ?string $orderedTo = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array{
     *     q: string|null,
     *     status: string|null,
     *     patient_id: string|null,
     *     provider_id: string|null,
     *     encounter_id: string|null,
     *     lab_test_id: string|null,
     *     lab_provider_key: string|null,
     *     ordered_from: string|null,
     *     ordered_to: string|null,
     *     created_from: string|null,
     *     created_to: string|null,
     *     limit: int
     * }
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'status' => $this->status,
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'encounter_id' => $this->encounterId,
            'lab_test_id' => $this->labTestId,
            'lab_provider_key' => $this->labProviderKey,
            'ordered_from' => $this->orderedFrom,
            'ordered_to' => $this->orderedTo,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
