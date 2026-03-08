<?php

namespace App\Modules\IdentityAccess\Application\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class IpAddressNotAllowedException extends AccessDeniedHttpException
{
    public function __construct()
    {
        parent::__construct('The current IP address is outside the active tenant allowlist.');
    }
}
