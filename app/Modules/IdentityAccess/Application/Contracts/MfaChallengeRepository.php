<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\MfaChallengeData;
use DateTimeInterface;

interface MfaChallengeRepository
{
    public function create(
        string $userId,
        DateTimeInterface $expiresAt,
        ?string $ipAddress,
        ?string $userAgent,
        string $purpose = 'login',
    ): MfaChallengeData;

    public function findActive(string $challengeId, DateTimeInterface $now): ?MfaChallengeData;

    public function markVerified(string $challengeId, DateTimeInterface $verifiedAt): bool;
}
