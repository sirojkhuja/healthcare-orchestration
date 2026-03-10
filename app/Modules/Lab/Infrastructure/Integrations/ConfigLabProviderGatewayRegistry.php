<?php

namespace App\Modules\Lab\Infrastructure\Integrations;

use App\Modules\Lab\Application\Contracts\LabProviderGateway;
use App\Modules\Lab\Application\Contracts\LabProviderGatewayRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConfigLabProviderGatewayRegistry implements LabProviderGatewayRegistry
{
    #[\Override]
    public function resolve(string $providerKey): LabProviderGateway
    {
        /** @var mixed $providers */
        $providers = config('medflow.lab.providers', []);

        if (! is_array($providers) || ! array_key_exists($providerKey, $providers) || ! is_array($providers[$providerKey])) {
            throw new NotFoundHttpException('The requested lab provider is not configured.');
        }

        /** @var array<string, mixed> $providerConfig */
        $providerConfig = $providers[$providerKey];
        /** @psalm-suppress MixedAssignment */
        $secretValue = $providerConfig['secret'] ?? null;
        $secret = is_string($secretValue) ? $secretValue : '';
        /** @psalm-suppress MixedAssignment */
        $externalOrderPrefixValue = $providerConfig['external_order_prefix'] ?? null;
        $externalOrderPrefix = is_string($externalOrderPrefixValue)
            ? $externalOrderPrefixValue
            : $providerKey;

        return new ConfigLabProviderGateway($providerKey, $secret, $externalOrderPrefix);
    }
}
