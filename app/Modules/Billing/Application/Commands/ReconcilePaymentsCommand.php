<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class ReconcilePaymentsCommand
{
    /**
     * @param  list<string>  $paymentIds
     */
    public function __construct(
        public string $providerKey,
        public array $paymentIds = [],
        public int $limit = 50,
    ) {}
}
