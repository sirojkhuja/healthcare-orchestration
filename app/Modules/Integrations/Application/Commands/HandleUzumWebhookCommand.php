<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class HandleUzumWebhookCommand
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $operation,
        public string $authorization,
        public string $rawPayload,
        public array $payload,
    ) {}
}
