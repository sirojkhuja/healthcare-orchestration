<?php

namespace App\Modules\Notifications\Infrastructure\Integrations;

final class EskizSmsProvider extends AbstractStubSmsProvider
{
    public function __construct(
        string $providerKey = 'eskiz',
        string $name = 'Eskiz',
        string $sender = 'MedFlow',
        string $messageIdPrefix = 'eskiz',
    ) {
        parent::__construct($providerKey, $name, $sender, $messageIdPrefix);
    }
}
