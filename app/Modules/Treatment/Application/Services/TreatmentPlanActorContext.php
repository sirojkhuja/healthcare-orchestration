<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanActor;

final class TreatmentPlanActorContext
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
    ) {}

    public function current(): TreatmentPlanActor
    {
        $current = $this->authenticatedRequestContext->current();

        return new TreatmentPlanActor(
            type: 'user',
            id: $current->user->id,
            name: $current->user->name,
        );
    }
}
