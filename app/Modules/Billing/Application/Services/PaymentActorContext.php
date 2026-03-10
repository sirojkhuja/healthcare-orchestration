<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Domain\Payments\PaymentActor;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;

final class PaymentActorContext
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
    ) {}

    public function current(): PaymentActor
    {
        $current = $this->authenticatedRequestContext->current();

        return new PaymentActor(
            type: 'user',
            id: $current->user->id,
            name: $current->user->name,
        );
    }
}
