<?php

namespace App\Modules\Billing\Application\Contracts;

interface PaymentGatewayRegistry
{
    public function resolve(string $providerKey): PaymentGateway;
}
