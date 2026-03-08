<?php

namespace App\Modules\IdentityAccess\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ApiKeyData
{
    public function __construct(
        public string $keyId,
        public string $userId,
        public string $name,
        public string $prefix,
        public string $tokenHash,
        public ?CarbonImmutable $lastUsedAt,
        public ?CarbonImmutable $expiresAt,
        public ?CarbonImmutable $revokedAt,
        public CarbonImmutable $createdAt,
    ) {}

    public function toView(): ApiKeyViewData
    {
        return new ApiKeyViewData(
            keyId: $this->keyId,
            name: $this->name,
            prefix: $this->prefix,
            lastUsedAt: $this->lastUsedAt,
            expiresAt: $this->expiresAt,
            revokedAt: $this->revokedAt,
            createdAt: $this->createdAt,
        );
    }
}
