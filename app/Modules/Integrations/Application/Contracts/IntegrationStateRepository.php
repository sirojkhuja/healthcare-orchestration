<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\IntegrationStateData;
use Carbon\CarbonImmutable;

interface IntegrationStateRepository
{
    public function get(string $tenantId, string $integrationKey): ?IntegrationStateData;

    public function saveEnabled(
        string $tenantId,
        string $integrationKey,
        bool $enabled,
        CarbonImmutable $now,
    ): IntegrationStateData;

    public function saveTestResult(
        string $tenantId,
        string $integrationKey,
        string $status,
        ?string $message,
        CarbonImmutable $testedAt,
    ): IntegrationStateData;
}
