<?php

namespace Tests\Fixtures\Notifications;

use App\Modules\Notifications\Application\Contracts\SmsProvider;
use App\Modules\Notifications\Application\Contracts\SmsProviderRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FakeSmsProviderRegistry implements SmsProviderRegistry
{
    /**
     * @var array<string, SmsProvider>
     */
    private array $providers = [];

    /**
     * @param  list<SmsProvider>  $providers
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * @return list<array{key: string, name: string}>
     */
    #[\Override]
    public function configuredProviders(): array
    {
        return array_values(array_map(
            static fn (SmsProvider $provider): array => [
                'key' => $provider->providerKey(),
                'name' => $provider->providerName(),
            ],
            $this->providers,
        ));
    }

    public function register(SmsProvider $provider): void
    {
        $this->providers[$provider->providerKey()] = $provider;
    }

    #[\Override]
    public function resolve(string $providerKey): SmsProvider
    {
        if (! array_key_exists($providerKey, $this->providers)) {
            throw new NotFoundHttpException('The SMS provider is not configured.');
        }

        return $this->providers[$providerKey];
    }
}
