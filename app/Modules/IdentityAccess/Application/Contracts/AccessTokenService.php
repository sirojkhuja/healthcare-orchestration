<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\AccessTokenPayload;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedUserData;
use DateTimeInterface;

interface AccessTokenService
{
    public function decode(string $token): AccessTokenPayload;

    public function issue(
        AuthenticatedUserData $user,
        string $sessionId,
        string $accessTokenId,
        DateTimeInterface $issuedAt,
        DateTimeInterface $expiresAt,
    ): string;
}
