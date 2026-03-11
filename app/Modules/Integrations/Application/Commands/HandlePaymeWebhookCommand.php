<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class HandlePaymeWebhookCommand
{
    public function __construct(
        public string $authorization,
        public string $rawPayload,
        public mixed $payload,
    ) {}
}
