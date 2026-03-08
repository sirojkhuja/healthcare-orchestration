<?php

namespace App\Shared\Application\Exceptions;

final class InvalidTenantContext extends TenantContextException
{
    #[\Override]
    public function statusCode(): int
    {
        return 400;
    }
}
