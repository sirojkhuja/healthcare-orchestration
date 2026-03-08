<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\AuthSessionData;
use DateTimeInterface;

interface AuthSessionRepository
{
    public function create(
        string $userId,
        string $refreshToken,
        DateTimeInterface $accessTokenExpiresAt,
        DateTimeInterface $refreshTokenExpiresAt,
        ?string $ipAddress,
        ?string $userAgent,
    ): AuthSessionData;

    public function findActiveByAccessToken(string $sessionId, string $accessTokenId, DateTimeInterface $now): ?AuthSessionData;

    public function findActiveByRefreshToken(string $refreshToken, DateTimeInterface $now): ?AuthSessionData;

    public function revoke(string $sessionId, DateTimeInterface $revokedAt): void;

    public function rotateRefreshToken(
        string $sessionId,
        string $refreshToken,
        DateTimeInterface $accessTokenExpiresAt,
        DateTimeInterface $refreshTokenExpiresAt,
        ?string $ipAddress,
        ?string $userAgent,
        DateTimeInterface $usedAt,
    ): ?AuthSessionData;

    public function touchUsage(string $sessionId, DateTimeInterface $usedAt): void;
}
