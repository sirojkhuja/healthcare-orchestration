<?php

namespace App\Modules\Billing\Application\Data;

final readonly class PaymentListCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $status = null,
        public ?string $invoiceId = null,
        public ?string $providerKey = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public int $limit = 25,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'status' => $this->status,
            'invoice_id' => $this->invoiceId,
            'provider_key' => $this->providerKey,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
            'limit' => $this->limit,
        ];
    }
}
