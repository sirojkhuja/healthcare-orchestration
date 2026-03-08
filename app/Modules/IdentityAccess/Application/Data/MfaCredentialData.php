<?php

namespace App\Modules\IdentityAccess\Application\Data;

use DateTimeInterface;

final readonly class MfaCredentialData
{
    /**
     * @param  list<string>  $recoveryCodeHashes
     */
    public function __construct(
        public string $credentialId,
        public string $userId,
        public ?string $secret,
        public array $recoveryCodeHashes,
        public ?DateTimeInterface $enabledAt,
        public ?DateTimeInterface $lastUsedAt,
        public ?DateTimeInterface $disabledAt,
        public DateTimeInterface $createdAt,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabledAt !== null && $this->disabledAt === null && $this->secret !== null;
    }
}
