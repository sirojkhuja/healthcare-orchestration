<?php

namespace App\Modules\Patient\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientConsentData
{
    public function __construct(
        public string $consentId,
        public string $patientId,
        public string $consentType,
        public string $grantedByName,
        public ?string $grantedByRelationship,
        public CarbonImmutable $grantedAt,
        public ?CarbonImmutable $expiresAt,
        public ?CarbonImmutable $revokedAt,
        public ?string $revocationReason,
        public ?string $notes,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    public function status(): string
    {
        if ($this->revokedAt instanceof CarbonImmutable) {
            return 'revoked';
        }

        if ($this->expiresAt instanceof CarbonImmutable && $this->expiresAt->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->consentId,
            'patient_id' => $this->patientId,
            'consent_type' => $this->consentType,
            'status' => $this->status(),
            'granted_by_name' => $this->grantedByName,
            'granted_by_relationship' => $this->grantedByRelationship,
            'granted_at' => $this->grantedAt->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'revoked_at' => $this->revokedAt?->toIso8601String(),
            'revocation_reason' => $this->revocationReason,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
