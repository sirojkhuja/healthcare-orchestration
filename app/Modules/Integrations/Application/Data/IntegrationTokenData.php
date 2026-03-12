<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class IntegrationTokenData
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $integrationKey,
        public string $label,
        public string $tokenType,
        public array $scopes,
        public ?string $accessTokenPreview,
        public ?string $refreshTokenPreview,
        public ?CarbonImmutable $accessTokenExpiresAt,
        public ?CarbonImmutable $refreshTokenExpiresAt,
        public ?CarbonImmutable $lastRefreshedAt,
        public ?CarbonImmutable $revokedAt,
        public array $metadata,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    public function status(): string
    {
        if ($this->revokedAt instanceof CarbonImmutable) {
            return 'revoked';
        }

        if ($this->accessTokenExpiresAt instanceof CarbonImmutable && $this->accessTokenExpiresAt->isPast()) {
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
            'id' => $this->id,
            'integration_key' => $this->integrationKey,
            'label' => $this->label,
            'status' => $this->status(),
            'token_type' => $this->tokenType,
            'scopes' => $this->scopes,
            'access_token_preview' => $this->accessTokenPreview,
            'refresh_token_preview' => $this->refreshTokenPreview,
            'access_token_expires_at' => $this->accessTokenExpiresAt?->toIso8601String(),
            'refresh_token_expires_at' => $this->refreshTokenExpiresAt?->toIso8601String(),
            'last_refreshed_at' => $this->lastRefreshedAt?->toIso8601String(),
            'revoked_at' => $this->revokedAt?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
