<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use DateTimeInterface;

interface MfaTotpService
{
    public function generateSecret(): string;

    public function codeAt(string $secret, DateTimeInterface $moment): string;

    public function verifyCode(string $secret, string $code, DateTimeInterface $moment): bool;

    public function provisioningUri(string $accountLabel, string $secret): string;

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array;

    public function recoveryCodeHash(string $recoveryCode): string;
}
