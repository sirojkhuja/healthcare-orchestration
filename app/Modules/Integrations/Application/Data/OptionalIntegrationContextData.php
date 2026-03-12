<?php

namespace App\Modules\Integrations\Application\Data;

final readonly class OptionalIntegrationContextData
{
    public function __construct(
        public string $tenantId,
        public string $integrationKey,
        public string $webhookId,
    ) {}
}
