<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\StoredIntegrationCredentialsData;
use Carbon\CarbonImmutable;

interface IntegrationCredentialRepository
{
    public function delete(string $tenantId, string $integrationKey): bool;

    public function get(string $tenantId, string $integrationKey): ?StoredIntegrationCredentialsData;

    /**
     * @param  array<string, string|null>  $values
     * @param  list<string>  $configuredFields
     */
    public function save(
        string $tenantId,
        string $integrationKey,
        array $values,
        array $configuredFields,
        CarbonImmutable $now,
    ): StoredIntegrationCredentialsData;
}
