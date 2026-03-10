<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\Lab\Domain\LabOrders\LabOrderActor;

final class LabOrderActorContext
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
    ) {}

    public function current(): LabOrderActor
    {
        $current = $this->authenticatedRequestContext->current();

        return new LabOrderActor(
            type: 'user',
            id: $current->user->id,
            name: $current->user->name,
        );
    }
}
