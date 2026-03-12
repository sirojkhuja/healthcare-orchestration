<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class HandleMyIdWebhookCommand
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $secret,
        public string $rawPayload,
        public array $payload,
    ) {}
}
