<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedRequestData;
use App\Modules\IdentityAccess\Application\Queries\GetMeQuery;

final class GetMeQueryHandler
{
    public function __construct(private readonly AuthenticatedRequestContext $authenticatedRequestContext) {}

    public function handle(GetMeQuery $query): AuthenticatedRequestData
    {
        return $this->authenticatedRequestContext->current();
    }
}
