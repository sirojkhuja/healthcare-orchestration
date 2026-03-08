<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class VerifyMfaCommand
{
    public function __construct(
        public ?string $challengeId,
        public ?string $code,
        public ?string $recoveryCode,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}
}
