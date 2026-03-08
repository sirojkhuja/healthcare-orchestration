<?php

namespace App\Shared\Application\Exceptions;

final class MissingTenantContext extends TenantContextException
{
    #[\Override]
    public function statusCode(): int
    {
        return 400;
    }
}
