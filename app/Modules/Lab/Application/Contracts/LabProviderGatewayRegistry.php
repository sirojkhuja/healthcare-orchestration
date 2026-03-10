<?php

namespace App\Modules\Lab\Application\Contracts;

interface LabProviderGatewayRegistry
{
    public function resolve(string $providerKey): LabProviderGateway;
}
