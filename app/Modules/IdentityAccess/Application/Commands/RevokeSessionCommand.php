<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class RevokeSessionCommand
{
    public function __construct(public string $sessionId) {}
}
