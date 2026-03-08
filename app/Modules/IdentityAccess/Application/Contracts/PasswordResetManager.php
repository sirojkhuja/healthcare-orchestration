<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\AuthenticatedUserData;

interface PasswordResetManager
{
    public function issueToken(string $email): void;

    public function reset(string $email, string $token, string $password): ?AuthenticatedUserData;
}
