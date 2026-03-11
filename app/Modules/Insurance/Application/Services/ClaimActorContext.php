<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\Insurance\Domain\Claims\ClaimActor;

final class ClaimActorContext
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
    ) {}

    public function current(): ClaimActor
    {
        $current = $this->authenticatedRequestContext->current();

        return new ClaimActor(
            type: 'user',
            id: $current->user->id,
            name: $current->user->name,
        );
    }
}
