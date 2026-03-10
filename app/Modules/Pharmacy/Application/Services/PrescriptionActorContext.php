<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\Pharmacy\Domain\Prescriptions\PrescriptionActor;

final class PrescriptionActorContext
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
    ) {}

    public function current(): PrescriptionActor
    {
        $current = $this->authenticatedRequestContext->current();

        return new PrescriptionActor(
            type: 'user',
            id: $current->user->id,
            name: $current->user->name,
        );
    }
}
