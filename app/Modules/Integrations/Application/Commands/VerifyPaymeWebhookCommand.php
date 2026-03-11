<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class VerifyPaymeWebhookCommand
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $authorization,
        public string $rawPayload,
        public array $payload,
    ) {}
}
