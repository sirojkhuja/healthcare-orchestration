<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\ApiKeyData;
use DateTimeInterface;

interface ApiKeyRepository
{
    public function create(
        string $keyId,
        string $userId,
        string $name,
        string $prefix,
        string $tokenHash,
        ?DateTimeInterface $expiresAt,
    ): ApiKeyData;

    public function findById(string $keyId): ?ApiKeyData;

    /**
     * @return list<ApiKeyData>
     */
    public function listForUser(string $userId): array;

    public function revokeForUser(string $keyId, string $userId, DateTimeInterface $revokedAt): bool;

    public function touchUsage(string $keyId, DateTimeInterface $usedAt): void;
}
