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
use App\Modules\Integrations\Application\Data\IntegrationHealthData;
use App\Modules\Integrations\Application\Data\IntegrationLogData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @phpstan-import-type IntegrationDefinition from IntegrationCatalog
 */
final class IntegrationDiagnosticsService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IntegrationCatalog $integrationCatalog,
        private readonly IntegrationStateRepository $integrationStateRepository,
        private readonly IntegrationCredentialRepository $integrationCredentialRepository,
        private readonly IntegrationWebhookRepository $integrationWebhookRepository,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly IntegrationLogRepository $integrationLogRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function health(string $integrationKey): IntegrationHealthData
    {
        $definition = $this->definitionOrFail($integrationKey);
        $tenantId = $this->tenantContext->requireTenantId();
        $enabled = $this->enabled($definition, $integrationKey);
        $available = $definition['available'];
        $stored = $this->integrationCredentialRepository->get($tenantId, $integrationKey);
        $webhooks = $this->integrationWebhookRepository->list($tenantId, $integrationKey);
        $token = $this->integrationTokenRepository->latestActive($tenantId, $integrationKey);
        $state = $this->integrationStateRepository->get($tenantId, $integrationKey);

        $checks = [
            $available
                ? ['key' => 'feature_flag', 'status' => 'pass', 'message' => 'Integration catalog entry is available.']
                : ['key' => 'feature_flag', 'status' => 'fail', 'message' => 'Integration feature flag is disabled.'],
        ];

        if ($definition['supports']['credentials']) {
            $requiredFields = $this->requiredCredentialFieldKeys($definition);
            $configuredFields = $stored !== null ? $stored->configuredFields : [];
            $missingFields = array_values(array_diff($requiredFields, $configuredFields));
            $checks[] = $missingFields === []
                ? ['key' => 'credentials', 'status' => 'pass', 'message' => 'Required credentials are configured.']
                : [
                    'key' => 'credentials',
                    'status' => 'fail',
                    'message' => 'Missing required credentials: '.implode(', ', $missingFields),
                ];
        }

        if ($definition['supports']['webhooks']) {
            $checks[] = $webhooks !== []
                ? ['key' => 'webhooks', 'status' => 'pass', 'message' => 'Webhook inventory exists.']
                : ['key' => 'webhooks', 'status' => 'warn', 'message' => 'No managed webhooks are registered.'];
        }

        if ($definition['supports']['tokens']) {
            $checks[] = match (true) {
                $token === null => ['key' => 'tokens', 'status' => 'warn', 'message' => 'No active integration token exists.'],
                $token->status() === 'expired' => ['key' => 'tokens', 'status' => 'fail', 'message' => 'The active token is expired.'],
                default => ['key' => 'tokens', 'status' => 'pass', 'message' => 'An active integration token exists.'],
            };
        }

        $status = $this->overallStatus($enabled, $available, $checks);

        return new IntegrationHealthData(
            integrationKey: $integrationKey,
            status: $status,
            enabled: $enabled,
            available: $available,
            lastTestStatus: $state?->lastTestStatus,
            lastTestedAt: $state?->lastTestedAt,
            checks: $checks,
        );
    }

    /**
     * @return list<IntegrationLogData>
     */
    public function listLogs(
        string $integrationKey,
        ?string $level = null,
        ?string $event = null,
        int $limit = 50,
    ): array {
        $this->definitionOrFail($integrationKey);

        return $this->integrationLogRepository->list(
            $this->tenantContext->requireTenantId(),
            $integrationKey,
            $level,
            $event,
            $limit,
        );
    }

    public function testConnection(string $integrationKey): IntegrationHealthData
    {
        $definition = $this->definitionOrFail($integrationKey);

        if (! $definition['supports']['test_connection']) {
            throw new ConflictHttpException('This integration does not support test-connection checks.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $health = $this->health($integrationKey);
        $now = CarbonImmutable::now();
        $currentState = $this->integrationStateRepository->get($tenantId, $integrationKey);

        if ($currentState === null) {
            $this->integrationStateRepository->saveEnabled($tenantId, $integrationKey, $health->enabled, $now);
        }

        $message = match ($health->status) {
            'healthy' => 'Integration readiness probe succeeded.',
            'disabled' => 'Integration readiness probe skipped because the integration is disabled.',
            'degraded' => 'Integration readiness probe completed with warnings.',
            default => 'Integration readiness probe failed.',
        };

        $this->integrationStateRepository->saveTestResult(
            $tenantId,
            $integrationKey,
            $health->status,
            $message,
            $now,
        );
        $this->integrationLogRepository->create(
            $tenantId,
            $integrationKey,
            $health->status === 'failing' ? 'error' : 'info',
            'integration.connection_tested',
            $message,
            [
                'status' => $health->status,
            ],
            $now,
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.connection_tested',
            objectType: 'integration',
            objectId: $integrationKey,
            after: [
                'status' => $health->status,
            ],
            metadata: [
                'integration_key' => $integrationKey,
            ],
        ));

        return $this->health($integrationKey);
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
     * @return list<string>
     */
    private function requiredCredentialFieldKeys(array $definition): array
    {
        $keys = [];

        foreach ($definition['credential_fields'] as $field) {
            if ($field['required']) {
                $keys[] = $field['key'];
            }
        }

        return $keys;
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

    /**
     * @param  list<array<string, string|null>>  $checks
     */
    private function overallStatus(bool $enabled, bool $available, array $checks): string
    {
        if (! $available || ! $enabled) {
            return 'disabled';
        }

        $statuses = array_map(static fn (array $check): string => $check['status'] ?? 'pass', $checks);

        if (in_array('fail', $statuses, true)) {
            return 'failing';
        }

        if (in_array('warn', $statuses, true)) {
            return 'degraded';
        }

        return 'healthy';
    }
}
