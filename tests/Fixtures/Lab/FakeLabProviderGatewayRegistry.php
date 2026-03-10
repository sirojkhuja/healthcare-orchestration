<?php

namespace Tests\Fixtures\Lab;

use App\Modules\Lab\Application\Contracts\LabProviderGateway;
use App\Modules\Lab\Application\Contracts\LabProviderGatewayRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FakeLabProviderGatewayRegistry implements LabProviderGatewayRegistry
{
    /**
     * @var array<string, LabProviderGateway>
     */
    private array $gateways = [];

    /**
     * @param  list<LabProviderGateway>  $gateways
     */
    public function __construct(array $gateways = [])
    {
        foreach ($gateways as $gateway) {
            $this->register($gateway);
        }
    }

    public function register(LabProviderGateway $gateway): void
    {
        $this->gateways[$gateway->providerKey()] = $gateway;
    }

    #[\Override]
    public function resolve(string $providerKey): LabProviderGateway
    {
        if (! array_key_exists($providerKey, $this->gateways)) {
            throw new NotFoundHttpException('The requested lab provider is not configured.');
        }

        return $this->gateways[$providerKey];
    }
}
