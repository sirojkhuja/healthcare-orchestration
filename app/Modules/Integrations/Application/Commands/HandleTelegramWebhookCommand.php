<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class HandleTelegramWebhookCommand
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $secretToken,
        public string $rawPayload,
        public array $payload,
    ) {}
}
