<?php

namespace App\Modules\Insurance\Application\Data;

final readonly class ClaimSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $payerId = null,
        public ?string $patientId = null,
        public ?string $invoiceId = null,
        public ?string $serviceDateFrom = null,
        public ?string $serviceDateTo = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array{
     *     q: string|null,
     *     status: string|null,
     *     payer_id: string|null,
     *     patient_id: string|null,
     *     invoice_id: string|null,
     *     service_date_from: string|null,
     *     service_date_to: string|null,
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
            'payer_id' => $this->payerId,
            'patient_id' => $this->patientId,
            'invoice_id' => $this->invoiceId,
            'service_date_from' => $this->serviceDateFrom,
            'service_date_to' => $this->serviceDateTo,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
