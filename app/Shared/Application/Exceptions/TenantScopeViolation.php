<?php

namespace App\Shared\Application\Exceptions;

final class TenantScopeViolation extends TenantContextException
{
    #[\Override]
    public function statusCode(): int
    {
        return 403;
    }
}
