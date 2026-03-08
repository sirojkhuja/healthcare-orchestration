<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\MfaCredentialData;
use DateTimeInterface;

interface MfaCredentialRepository
{
    /**
     * @param  list<string>  $recoveryCodeHashes
     */
    public function upsertPending(
        string $userId,
        string $secret,
        array $recoveryCodeHashes,
        DateTimeInterface $now,
    ): MfaCredentialData;

    public function findForUser(string $userId): ?MfaCredentialData;

    public function findEnabledForUser(string $userId): ?MfaCredentialData;

    public function enable(string $credentialId, DateTimeInterface $enabledAt): ?MfaCredentialData;

    public function disable(string $credentialId, DateTimeInterface $disabledAt): bool;

    public function consumeRecoveryCode(string $credentialId, string $recoveryCodeHash, DateTimeInterface $usedAt): bool;

    public function touchLastUsed(string $credentialId, DateTimeInterface $usedAt): void;
}
