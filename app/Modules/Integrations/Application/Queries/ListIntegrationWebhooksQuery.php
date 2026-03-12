<?php

namespace App\Modules\Integrations\Application\Queries;

final readonly class ListIntegrationWebhooksQuery
{
    public function __construct(
        public string $integrationKey,
    ) {}
}
