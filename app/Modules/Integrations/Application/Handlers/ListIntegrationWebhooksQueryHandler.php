<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Data\IntegrationWebhookData;
use App\Modules\Integrations\Application\Queries\ListIntegrationWebhooksQuery;
use App\Modules\Integrations\Application\Services\IntegrationWebhookService;

final class ListIntegrationWebhooksQueryHandler
{
    public function __construct(
        private readonly IntegrationWebhookService $integrationWebhookService,
    ) {}

    /**
     * @return list<IntegrationWebhookData>
     */
    public function handle(ListIntegrationWebhooksQuery $query): array
    {
        return $this->integrationWebhookService->list($query->integrationKey);
    }
}
