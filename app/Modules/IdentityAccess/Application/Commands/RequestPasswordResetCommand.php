<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class RequestPasswordResetCommand
{
    public function __construct(public string $email) {}
}
