<?php

namespace App\Modules\IdentityAccess\Application\Data;

use DateTimeInterface;

final readonly class AccessTokenPayload
{
    public function __construct(
        public string $userId,
        public string $sessionId,
        public string $accessTokenId,
        public DateTimeInterface $issuedAt,
        public DateTimeInterface $expiresAt,
    ) {}
}
