<?php

namespace App\Modules\Notifications\Infrastructure\Integrations;

final class TextUpSmsProvider extends AbstractStubSmsProvider
{
    public function __construct(
        string $providerKey = 'textup',
        string $name = 'TextUp',
        string $sender = 'MedFlow',
        string $messageIdPrefix = 'textup',
    ) {
        parent::__construct($providerKey, $name, $sender, $messageIdPrefix);
    }
}
