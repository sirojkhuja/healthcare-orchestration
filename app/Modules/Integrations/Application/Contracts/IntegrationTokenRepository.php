<?php

namespace App\Modules\Integrations\Application\Contracts;

use App\Modules\Integrations\Application\Data\IntegrationTokenData;
use Carbon\CarbonImmutable;

interface IntegrationTokenRepository
{
    /**
     * @return list<IntegrationTokenData>
     */
    public function list(string $tenantId, string $integrationKey): array;

    /**
     * @param  array<string, string|null>  $credentialValues
     */
    public function materializeFromCredentials(
        string $tenantId,
        string $integrationKey,
        array $credentialValues,
        CarbonImmutable $now,
    ): ?IntegrationTokenData;

    public function findInTenant(string $tenantId, string $integrationKey, string $tokenId): ?IntegrationTokenData;

    public function latestActive(string $tenantId, string $integrationKey): ?IntegrationTokenData;

    public function refresh(
        string $tenantId,
        string $integrationKey,
        string $tokenId,
        string $accessToken,
        ?CarbonImmutable $accessTokenExpiresAt,
        CarbonImmutable $refreshedAt,
    ): ?IntegrationTokenData;

    public function revoke(
        string $tenantId,
        string $integrationKey,
        string $tokenId,
        CarbonImmutable $revokedAt,
    ): ?IntegrationTokenData;

    public function revokeAll(string $tenantId, string $integrationKey, CarbonImmutable $revokedAt): int;
}
