<?php

namespace App\Modules\IdentityAccess\Application\Data;

use DateTimeInterface;

final readonly class AuthSessionViewData
{
    public function __construct(
        public string $id,
        public bool $current,
        public ?string $ipAddress,
        public ?string $userAgent,
        public DateTimeInterface $accessTokenExpiresAt,
        public DateTimeInterface $refreshTokenExpiresAt,
        public ?DateTimeInterface $lastUsedAt,
        public ?DateTimeInterface $revokedAt,
        public DateTimeInterface $createdAt,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     current: bool,
     *     ip_address: string|null,
     *     user_agent: string|null,
     *     access_token_expires_at: string,
     *     refresh_token_expires_at: string,
     *     last_used_at: string|null,
     *     revoked_at: string|null,
     *     created_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'current' => $this->current,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'access_token_expires_at' => $this->accessTokenExpiresAt->format(DATE_ATOM),
            'refresh_token_expires_at' => $this->refreshTokenExpiresAt->format(DATE_ATOM),
            'last_used_at' => $this->lastUsedAt?->format(DATE_ATOM),
            'revoked_at' => $this->revokedAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
