<?php

namespace App\Modules\IdentityAccess\Application\Data;

use DateTimeInterface;

final readonly class MfaChallengeData
{
    public function __construct(
        public string $challengeId,
        public string $userId,
        public string $purpose,
        public ?string $ipAddress,
        public ?string $userAgent,
        public DateTimeInterface $expiresAt,
        public ?DateTimeInterface $verifiedAt,
        public DateTimeInterface $createdAt,
    ) {}
}
