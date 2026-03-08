<?php

namespace App\Modules\IdentityAccess\Application\Exceptions;

use Illuminate\Auth\AuthenticationException;

final class RevokedApiKeyException extends AuthenticationException
{
    public function __construct()
    {
        parent::__construct('The presented API key has been revoked.');
    }
}
