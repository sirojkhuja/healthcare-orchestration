<?php

namespace App\Modules\Notifications\Infrastructure\Integrations;

use App\Modules\Notifications\Application\Contracts\SmsProvider;
use App\Modules\Notifications\Application\Contracts\SmsProviderRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ConfigSmsProviderRegistry implements SmsProviderRegistry
{
    public function __construct(
        private readonly Container $container,
    ) {}

    #[\Override]
    public function configuredProviders(): array
    {
        /** @var mixed $providers */
        $providers = config('notifications.sms.providers', []);

        if (! is_array($providers)) {
            return [];
        }

        $configured = [];

        foreach ($providers as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $nameValue = $definition['name'] ?? null;
            $configured[] = [
                'key' => $key,
                'name' => is_string($nameValue) && trim($nameValue) !== '' ? trim($nameValue) : Str::headline($key),
            ];
        }

        return $configured;
    }

    #[\Override]
    public function resolve(string $providerKey): SmsProvider
    {
        /** @var array<string, mixed>|null $definition */
        $definition = config('notifications.sms.providers.'.$providerKey);

        if (! is_array($definition)) {
            throw new UnprocessableEntityHttpException('The SMS provider is not configured.');
        }

        $driver = $definition['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            throw new UnprocessableEntityHttpException('The configured SMS provider driver is invalid.');
        }

        /** @var array<string, mixed> $parameters */
        $parameters = ['providerKey' => $providerKey];

        /** @psalm-suppress MixedAssignment */
        foreach ($definition as $key => $value) {
            if ($key === 'driver') {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $parameters[Str::camel($key)] = $value;
        }

        $provider = $this->container->make($driver, $parameters);

        if (! $provider instanceof SmsProvider) {
            throw new UnprocessableEntityHttpException('The configured SMS provider driver is invalid.');
        }

        return $provider;
    }
}
