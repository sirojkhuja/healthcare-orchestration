<?php

namespace App\Modules\IdentityAccess\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ApiKeyViewData
{
    public function __construct(
        public string $keyId,
        public string $name,
        public string $prefix,
        public ?CarbonImmutable $lastUsedAt,
        public ?CarbonImmutable $expiresAt,
        public ?CarbonImmutable $revokedAt,
        public CarbonImmutable $createdAt,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     prefix: string,
     *     last_used_at: string|null,
     *     expires_at: string|null,
     *     revoked_at: string|null,
     *     created_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->keyId,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'last_used_at' => $this->lastUsedAt?->format(DATE_ATOM),
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
            'revoked_at' => $this->revokedAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
