<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\AuthenticatedRequestData;

interface AuthenticatedRequestContext
{
    public function current(): AuthenticatedRequestData;
}
