<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class VerifyClickWebhookCommand
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $rawPayload,
        public array $payload,
    ) {}
}
