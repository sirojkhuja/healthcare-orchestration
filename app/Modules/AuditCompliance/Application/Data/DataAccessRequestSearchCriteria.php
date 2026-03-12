<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class DataAccessRequestSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $patientId = null,
        public ?string $requestType = null,
        public ?string $status = null,
        public ?CarbonImmutable $requestedFrom = null,
        public ?CarbonImmutable $requestedTo = null,
        public int $limit = 50,
    ) {}

    public function normalizedQuery(): ?string
    {
        if ($this->query === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($this->query));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'patient_id' => $this->patientId,
            'request_type' => $this->requestType,
            'status' => $this->status,
            'requested_from' => $this->requestedFrom?->toIso8601String(),
            'requested_to' => $this->requestedTo?->toIso8601String(),
            'limit' => $this->limit,
        ];
    }
}
