<?php

namespace App\Modules\Provider\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderLicenseData
{
    public function __construct(
        public string $licenseId,
        public string $tenantId,
        public string $providerId,
        public string $licenseType,
        public string $licenseNumber,
        public string $issuingAuthority,
        public ?string $jurisdiction,
        public ?CarbonImmutable $issuedOn,
        public ?CarbonImmutable $expiresOn,
        public ?string $notes,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    public function status(): string
    {
        if (
            $this->expiresOn instanceof CarbonImmutable
            && CarbonImmutable::today()->greaterThan($this->expiresOn->startOfDay())
        ) {
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
            'id' => $this->licenseId,
            'tenant_id' => $this->tenantId,
            'provider_id' => $this->providerId,
            'license_type' => $this->licenseType,
            'license_number' => $this->licenseNumber,
            'issuing_authority' => $this->issuingAuthority,
            'jurisdiction' => $this->jurisdiction,
            'issued_on' => $this->issuedOn?->toDateString(),
            'expires_on' => $this->expiresOn?->toDateString(),
            'status' => $this->status(),
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
