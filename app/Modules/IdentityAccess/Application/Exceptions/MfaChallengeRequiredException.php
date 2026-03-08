<?php

namespace App\Modules\IdentityAccess\Application\Exceptions;

use DateTimeInterface;
use Illuminate\Auth\AuthenticationException;

final class MfaChallengeRequiredException extends AuthenticationException
{
    public function __construct(
        public readonly string $challengeId,
        public readonly DateTimeInterface $expiresAt,
    ) {
        parent::__construct('Multi-factor authentication is required to complete login.');
    }
}
