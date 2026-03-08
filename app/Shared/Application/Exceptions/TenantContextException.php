<?php

namespace App\Shared\Application\Exceptions;

use RuntimeException;

abstract class TenantContextException extends RuntimeException
{
    abstract public function statusCode(): int;
}
