<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Integrations\Application\Contracts\IntegrationCatalog;
use App\Modules\Integrations\Application\Contracts\IntegrationCredentialRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationLogRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationStateRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationTokenRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationWebhookRepository;
use App\Modules\Integrations\Application\Data\IntegrationData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @phpstan-import-type IntegrationDefinition from IntegrationCatalog
 */
final class IntegrationRegistryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IntegrationCatalog $integrationCatalog,
        private readonly IntegrationStateRepository $integrationStateRepository,
        private readonly IntegrationCredentialRepository $integrationCredentialRepository,
        private readonly IntegrationWebhookRepository $integrationWebhookRepository,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly IntegrationLogRepository $integrationLogRepository,
        private readonly IntegrationDiagnosticsService $integrationDiagnosticsService,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @return list<IntegrationData>
     */
    public function list(?string $category = null, ?bool $enabled = null): array
    {
        $items = [];

        foreach ($this->integrationCatalog->all() as $definition) {
            $item = $this->buildData($definition);

            if ($category !== null && $item->category !== $category) {
                continue;
            }

            if ($enabled !== null && $item->enabled !== $enabled) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    public function show(string $integrationKey): IntegrationData
    {
        return $this->buildData($this->definitionOrFail($integrationKey));
    }

    public function disable(string $integrationKey): IntegrationData
    {
        $definition = $this->definitionOrFail($integrationKey);
        $tenantId = $this->tenantContext->requireTenantId();
        $now = CarbonImmutable::now();

        $this->integrationStateRepository->saveEnabled($tenantId, $integrationKey, false, $now);
        $this->integrationLogRepository->create(
            $tenantId,
            $integrationKey,
            'info',
            'integration.disabled',
            'Integration disabled for tenant.',
            [],
            $now,
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.disabled',
            objectType: 'integration',
            objectId: $integrationKey,
            after: ['enabled' => false],
            metadata: [
                'integration_key' => $integrationKey,
                'category' => $definition['category'],
            ],
        ));

        return $this->buildData($definition);
    }

    public function enable(string $integrationKey): IntegrationData
    {
        $definition = $this->definitionOrFail($integrationKey);

        if (! $definition['available']) {
            throw new ConflictHttpException('The requested integration is disabled by feature flag.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $now = CarbonImmutable::now();

        $this->integrationStateRepository->saveEnabled($tenantId, $integrationKey, true, $now);
        $this->integrationLogRepository->create(
            $tenantId,
            $integrationKey,
            'info',
            'integration.enabled',
            'Integration enabled for tenant.',
            [],
            $now,
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.enabled',
            objectType: 'integration',
            objectId: $integrationKey,
            after: ['enabled' => true],
            metadata: [
                'integration_key' => $integrationKey,
                'category' => $definition['category'],
            ],
        ));

        return $this->buildData($definition);
    }

    /**
     * @param  IntegrationDefinition  $definition
     */
    private function buildData(array $definition): IntegrationData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $integrationKey = $definition['integration_key'];
        $credentials = $this->integrationCredentialRepository->get($tenantId, $integrationKey);
        $health = $this->integrationDiagnosticsService->health($integrationKey);

        return new IntegrationData(
            integrationKey: $integrationKey,
            name: $definition['name'],
            category: $definition['category'],
            enabled: $this->enabled($definition, $integrationKey),
            available: $definition['available'],
            featureFlag: $definition['feature_flag'],
            capabilities: $definition['supports'],
            credentialSummary: [
                'supported' => $definition['supports']['credentials'],
                'configured' => $credentials !== null && $credentials->configuredFields !== [],
                'updated_at' => $credentials?->updatedAt->toIso8601String(),
            ],
            healthStatus: $health->status,
            webhookCount: count($this->integrationWebhookRepository->list($tenantId, $integrationKey)),
            tokenCount: count($this->integrationTokenRepository->list($tenantId, $integrationKey)),
        );
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

    /**
     * @param  IntegrationDefinition  $definition
     */
    private function enabled(array $definition, string $integrationKey): bool
    {
        $state = $this->integrationStateRepository->get($this->tenantContext->requireTenantId(), $integrationKey);

        if ($state !== null) {
            return $state->enabled;
        }

        return $definition['available'] && $definition['default_enabled'];
    }
}
