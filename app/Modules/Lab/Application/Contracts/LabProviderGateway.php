<?php

namespace App\Modules\Lab\Application\Contracts;

use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\LabProviderDispatchData;
use App\Modules\Lab\Application\Data\LabProviderOrderPayload;

interface LabProviderGateway
{
    public function providerKey(): string;

    public function reconcileOrder(LabOrderData $order): LabProviderOrderPayload;

    public function sendOrder(LabOrderData $order): LabProviderDispatchData;

    public function verifyWebhookSignature(string $signature, string $payload): bool;
}
