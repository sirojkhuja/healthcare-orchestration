<?php

namespace App\Modules\Billing\Infrastructure\Integrations;

use App\Modules\Billing\Application\Contracts\PaymentGateway;
use App\Modules\Billing\Application\Contracts\PaymentGatewayRegistry;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ConfigPaymentGatewayRegistry implements PaymentGatewayRegistry
{
    public function __construct(
        private readonly Container $container,
    ) {}

    #[\Override]
    public function resolve(string $providerKey): PaymentGateway
    {
        /** @var array<string, mixed>|null $definition */
        $definition = config('billing.payment_gateways.'.$providerKey);

        if (! is_array($definition)) {
            throw new UnprocessableEntityHttpException('The provider_key field must reference a configured payment gateway.');
        }

        $driver = $definition['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            throw new UnprocessableEntityHttpException('The configured payment gateway driver is invalid.');
        }

        $gateway = $this->container->make($driver, [
            'providerKey' => $providerKey,
            'supportsRefunds' => is_bool($definition['supports_refunds'] ?? null)
                ? $definition['supports_refunds']
                : false,
        ]);

        if (! $gateway instanceof PaymentGateway) {
            throw new UnprocessableEntityHttpException('The configured payment gateway driver is invalid.');
        }

        return $gateway;
    }
}
