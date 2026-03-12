<?php

namespace App\Modules\Billing\Application\Contracts;

interface ServiceIdAwareWebhookPaymentGateway extends PaymentGateway
{
    public function configuredServiceId(): string;
}
