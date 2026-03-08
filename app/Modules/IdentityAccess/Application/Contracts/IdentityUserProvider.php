<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\AuthenticatedUserData;

interface IdentityUserProvider
{
    public function attempt(string $email, string $password): ?AuthenticatedUserData;

    public function findById(string $userId): ?AuthenticatedUserData;
}
