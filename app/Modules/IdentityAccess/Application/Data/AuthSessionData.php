<?php

namespace App\Modules\IdentityAccess\Application\Data;

use DateTimeInterface;

final readonly class AuthSessionData
{
    public function __construct(
        public string $sessionId,
        public string $userId,
        public string $accessTokenId,
        public DateTimeInterface $accessTokenExpiresAt,
        public DateTimeInterface $refreshTokenExpiresAt,
        public ?DateTimeInterface $lastUsedAt,
        public ?DateTimeInterface $revokedAt,
    ) {}
}
