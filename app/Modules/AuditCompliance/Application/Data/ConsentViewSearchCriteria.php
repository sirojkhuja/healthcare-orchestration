<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ConsentViewSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?string $patientId = null,
        public ?string $consentType = null,
        public ?string $status = null,
        public ?CarbonImmutable $grantedFrom = null,
        public ?CarbonImmutable $grantedTo = null,
        public ?CarbonImmutable $expiresFrom = null,
        public ?CarbonImmutable $expiresTo = null,
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
            'consent_type' => $this->consentType,
            'status' => $this->status,
            'granted_from' => $this->grantedFrom?->toIso8601String(),
            'granted_to' => $this->grantedTo?->toIso8601String(),
            'expires_from' => $this->expiresFrom?->toIso8601String(),
            'expires_to' => $this->expiresTo?->toIso8601String(),
            'limit' => $this->limit,
        ];
    }
}
