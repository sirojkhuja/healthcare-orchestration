<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Domain\Invoices\InvoiceActor;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;

final class InvoiceActorContext
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
    ) {}

    public function current(): InvoiceActor
    {
        $current = $this->authenticatedRequestContext->current();

        return new InvoiceActor(
            type: 'user',
            id: $current->user->id,
            name: $current->user->name,
        );
    }
}
