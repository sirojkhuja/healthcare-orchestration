<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class RotateWebhookSecretCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $integrationKey,
        public string $webhookId,
        public array $attributes,
    ) {}
}
