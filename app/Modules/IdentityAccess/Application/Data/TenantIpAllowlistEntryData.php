<?php

namespace App\Modules\IdentityAccess\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TenantIpAllowlistEntryData
{
    public function __construct(
        public string $entryId,
        public string $cidr,
        public ?string $label,
        public CarbonImmutable $createdAt,
    ) {}

    /**
     * @return array{id: string, cidr: string, label: string|null, created_at: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->entryId,
            'cidr' => $this->cidr,
            'label' => $this->label,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
