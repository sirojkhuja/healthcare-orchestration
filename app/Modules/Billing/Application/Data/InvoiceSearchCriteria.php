<?php

namespace App\Modules\Billing\Application\Data;

final readonly class InvoiceSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $patientId = null,
        public ?string $issuedFrom = null,
        public ?string $issuedTo = null,
        public ?string $dueFrom = null,
        public ?string $dueTo = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array{
     *     q: string|null,
     *     status: string|null,
     *     patient_id: string|null,
     *     issued_from: string|null,
     *     issued_to: string|null,
     *     due_from: string|null,
     *     due_to: string|null,
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
            'issued_from' => $this->issuedFrom,
            'issued_to' => $this->issuedTo,
            'due_from' => $this->dueFrom,
            'due_to' => $this->dueTo,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
