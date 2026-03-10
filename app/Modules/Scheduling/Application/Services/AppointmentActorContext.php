<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\Scheduling\Domain\Appointments\AppointmentActor;

final class AppointmentActorContext
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
    ) {}

    public function current(): AppointmentActor
    {
        $current = $this->authenticatedRequestContext->current();

        return new AppointmentActor(
            type: 'user',
            id: $current->user->id,
            name: $current->user->name,
        );
    }
}
