<?php

namespace App\Modules\Notifications\Application\Contracts;

interface SmsProviderRegistry
{
    /**
     * @return list<array{key: string, name: string}>
     */
    public function configuredProviders(): array;

    public function resolve(string $providerKey): SmsProvider;
}
