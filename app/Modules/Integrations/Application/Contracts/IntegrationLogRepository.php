<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\IntegrationLogData;
use Carbon\CarbonImmutable;

interface IntegrationLogRepository
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function create(
        string $tenantId,
        string $integrationKey,
        string $level,
        string $event,
        string $message,
        array $context,
        CarbonImmutable $createdAt,
    ): IntegrationLogData;

    /**
     * @return list<IntegrationLogData>
     */
    public function list(
        string $tenantId,
        string $integrationKey,
        ?string $level = null,
        ?string $event = null,
        int $limit = 50,
    ): array;
}
