<?php

namespace App\Modules\Notifications\Infrastructure\Integrations;

final class PlayMobileSmsProvider extends AbstractStubSmsProvider
{
    public function __construct(
        string $providerKey = 'playmobile',
        string $name = 'Play Mobile',
        string $sender = 'MedFlow',
        string $messageIdPrefix = 'playmobile',
    ) {
        parent::__construct($providerKey, $name, $sender, $messageIdPrefix);
    }
}
