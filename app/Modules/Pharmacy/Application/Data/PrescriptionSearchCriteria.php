<?php

namespace App\Modules\Pharmacy\Application\Data;

final readonly class PrescriptionSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $patientId = null,
        public ?string $providerId = null,
        public ?string $encounterId = null,
        public ?string $issuedFrom = null,
        public ?string $issuedTo = null,
        public ?string $startFrom = null,
        public ?string $startTo = null,
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
     *     issued_from: string|null,
     *     issued_to: string|null,
     *     start_from: string|null,
     *     start_to: string|null,
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
            'issued_from' => $this->issuedFrom,
            'issued_to' => $this->issuedTo,
            'start_from' => $this->startFrom,
            'start_to' => $this->startTo,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
