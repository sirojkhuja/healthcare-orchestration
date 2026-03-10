<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class ReceiveLabResultWebhookCommand
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $providerKey,
        public string $signature,
        public string $rawPayload,
        public array $payload,
    ) {}
}
