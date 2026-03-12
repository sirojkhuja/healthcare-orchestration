<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Integrations\Application\Contracts\IntegrationCatalog;
use App\Modules\Integrations\Application\Contracts\IntegrationLogRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationTokenRepository;
use App\Modules\Integrations\Application\Data\IntegrationTokenData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @phpstan-import-type IntegrationDefinition from IntegrationCatalog
 */
final class IntegrationTokenService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IntegrationCatalog $integrationCatalog,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly IntegrationLogRepository $integrationLogRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @return list<IntegrationTokenData>
     */
    public function list(string $integrationKey): array
    {
        $definition = $this->definitionOrFail($integrationKey);

        return $definition['supports']['tokens']
            ? $this->integrationTokenRepository->list($this->tenantContext->requireTenantId(), $integrationKey)
            : [];
    }

    public function refresh(string $integrationKey, ?string $tokenId = null): IntegrationTokenData
    {
        $definition = $this->definitionOrFail($integrationKey);

        if (! $definition['supports']['tokens']) {
            throw new ConflictHttpException('This integration does not support managed tokens.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $token = $tokenId !== null
            ? $this->integrationTokenRepository->findInTenant($tenantId, $integrationKey, $tokenId)
            : $this->integrationTokenRepository->latestActive($tenantId, $integrationKey);

        if (! $token instanceof IntegrationTokenData) {
            throw new ConflictHttpException('No refreshable token exists for this integration.');
        }

        if ($token->refreshTokenPreview === null) {
            throw new ConflictHttpException('The selected token does not contain a refresh token.');
        }

        if ($token->refreshTokenExpiresAt instanceof CarbonImmutable && $token->refreshTokenExpiresAt->isPast()) {
            throw new ConflictHttpException('The selected refresh token is expired.');
        }

        $now = CarbonImmutable::now();
        $refreshed = $this->integrationTokenRepository->refresh(
            $tenantId,
            $integrationKey,
            $token->id,
            'itg_'.bin2hex(random_bytes(16)),
            $now->addMinutes(max(1, config()->integer('integrations.token_refresh_ttl_minutes', 60))),
            $now,
        );

        if (! $refreshed instanceof IntegrationTokenData) {
            throw new \LogicException('The refreshed integration token could not be reloaded.');
        }

        $this->integrationLogRepository->create(
            $tenantId,
            $integrationKey,
            'info',
            'integration.token_refreshed',
            'Integration token refreshed.',
            [
                'token_id' => $refreshed->id,
            ],
            $now,
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.token_refreshed',
            objectType: 'integration_token',
            objectId: $refreshed->id,
            after: [
                'status' => $refreshed->status(),
            ],
            metadata: [
                'integration_key' => $integrationKey,
                'token_id' => $refreshed->id,
            ],
        ));

        return $refreshed;
    }

    public function revoke(string $integrationKey, string $tokenId): IntegrationTokenData
    {
        $definition = $this->definitionOrFail($integrationKey);

        if (! $definition['supports']['tokens']) {
            throw new ConflictHttpException('This integration does not support managed tokens.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $token = $this->integrationTokenRepository->revoke($tenantId, $integrationKey, $tokenId, CarbonImmutable::now());

        if (! $token instanceof IntegrationTokenData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->integrationLogRepository->create(
            $tenantId,
            $integrationKey,
            'warning',
            'integration.token_revoked',
            'Integration token revoked.',
            [
                'token_id' => $token->id,
            ],
            CarbonImmutable::now(),
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.token_revoked',
            objectType: 'integration_token',
            objectId: $token->id,
            after: [
                'status' => $token->status(),
            ],
            metadata: [
                'integration_key' => $integrationKey,
                'token_id' => $token->id,
            ],
        ));

        return $token;
    }

    /**
     * @return IntegrationDefinition
     */
    private function definitionOrFail(string $integrationKey): array
    {
        $definition = $this->integrationCatalog->find($integrationKey);

        if ($definition === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $definition;
    }
}
