<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Integrations\Application\Contracts\IntegrationCatalog;
use App\Modules\Integrations\Application\Contracts\IntegrationLogRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationWebhookRepository;
use App\Modules\Integrations\Application\Data\IntegrationWebhookData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @phpstan-import-type IntegrationDefinition from IntegrationCatalog
 * @phpstan-import-type IntegrationWebhookConfig from IntegrationCatalog
 */
final class IntegrationWebhookService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IntegrationCatalog $integrationCatalog,
        private readonly IntegrationWebhookRepository $integrationWebhookRepository,
        private readonly IntegrationLogRepository $integrationLogRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $integrationKey, array $attributes): IntegrationWebhookData
    {
        $definition = $this->definitionOrFail($integrationKey);
        $webhookConfig = $this->webhookConfigOrFail($definition);
        $tenantId = $this->tenantContext->requireTenantId();
        $now = CarbonImmutable::now();
        $name = $this->requiredString($attributes['name'] ?? null, 'name');
        $rotateSupported = $webhookConfig['rotate_supported'];
        $secret = $rotateSupported ? $this->normalizedSecret($attributes['secret'] ?? null) ?? $this->generatedSecret() : null;

        $webhook = $this->integrationWebhookRepository->create(
            tenantId: $tenantId,
            integrationKey: $integrationKey,
            name: $name,
            endpointUrl: $this->endpointUrl($webhookConfig['path']),
            authMode: $webhookConfig['auth_mode'],
            secret: $secret,
            secretHash: $secret !== null ? hash('sha256', $secret) : null,
            secretLastRotatedAt: $secret !== null ? $now : null,
            status: $this->normalizedStatus($attributes['status'] ?? null),
            metadata: $this->metadata($attributes['metadata'] ?? null),
            now: $now,
        );

        $this->integrationLogRepository->create(
            $tenantId,
            $integrationKey,
            'info',
            'integration.webhook_created',
            'Integration webhook registration created.',
            [
                'webhook_id' => $webhook->id,
            ],
            $now,
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.webhook_created',
            objectType: 'integration_webhook',
            objectId: $webhook->id,
            after: [
                'name' => $webhook->name,
                'auth_mode' => $webhook->authMode,
            ],
            metadata: [
                'integration_key' => $integrationKey,
                'webhook_id' => $webhook->id,
            ],
        ));

        return $this->withCatalogWebhookFlags($webhook, $rotateSupported, $secret);
    }

    public function delete(string $integrationKey, string $webhookId): IntegrationWebhookData
    {
        $definition = $this->definitionOrFail($integrationKey);
        $webhookConfig = $this->webhookConfigOrFail($definition);
        $tenantId = $this->tenantContext->requireTenantId();
        $webhook = $this->integrationWebhookRepository->findInTenant($tenantId, $integrationKey, $webhookId);

        if (! $webhook instanceof IntegrationWebhookData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->integrationWebhookRepository->delete($tenantId, $integrationKey, $webhookId);
        $this->integrationLogRepository->create(
            $tenantId,
            $integrationKey,
            'warning',
            'integration.webhook_deleted',
            'Integration webhook registration deleted.',
            [
                'webhook_id' => $webhook->id,
            ],
            CarbonImmutable::now(),
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.webhook_deleted',
            objectType: 'integration_webhook',
            objectId: $webhook->id,
            before: [
                'name' => $webhook->name,
                'auth_mode' => $webhook->authMode,
            ],
            metadata: [
                'integration_key' => $integrationKey,
                'rotate_supported' => $webhookConfig['rotate_supported'],
            ],
        ));

        return $this->withCatalogWebhookFlags($webhook, $webhookConfig['rotate_supported']);
    }

    /**
     * @return list<IntegrationWebhookData>
     */
    public function list(string $integrationKey): array
    {
        $definition = $this->definitionOrFail($integrationKey);
        $webhookConfig = $definition['webhook'];
        $rotateSupported = $webhookConfig !== null && $webhookConfig['rotate_supported'];

        return array_map(
            fn (IntegrationWebhookData $webhook): IntegrationWebhookData => $this->withCatalogWebhookFlags($webhook, $rotateSupported),
            $this->integrationWebhookRepository->list($this->tenantContext->requireTenantId(), $integrationKey),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function rotateSecret(string $integrationKey, string $webhookId, array $attributes): IntegrationWebhookData
    {
        $definition = $this->definitionOrFail($integrationKey);
        $webhookConfig = $this->webhookConfigOrFail($definition);

        if (! $webhookConfig['rotate_supported']) {
            throw new ConflictHttpException('This integration webhook does not support secret rotation.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $existing = $this->integrationWebhookRepository->findInTenant($tenantId, $integrationKey, $webhookId);

        if (! $existing instanceof IntegrationWebhookData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $now = CarbonImmutable::now();
        $secret = $this->normalizedSecret($attributes['secret'] ?? null) ?? $this->generatedSecret();
        $rotated = $this->integrationWebhookRepository->updateSecret(
            $tenantId,
            $integrationKey,
            $webhookId,
            $secret,
            hash('sha256', $secret),
            $now,
            $now,
        );

        if (! $rotated instanceof IntegrationWebhookData) {
            throw new \LogicException('The rotated webhook could not be reloaded.');
        }

        $this->integrationLogRepository->create(
            $tenantId,
            $integrationKey,
            'warning',
            'integration.webhook_secret_rotated',
            'Integration webhook secret rotated.',
            [
                'webhook_id' => $rotated->id,
            ],
            $now,
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.webhook_secret_rotated',
            objectType: 'integration_webhook',
            objectId: $rotated->id,
            metadata: [
                'integration_key' => $integrationKey,
                'webhook_id' => $rotated->id,
            ],
        ));

        return $this->withCatalogWebhookFlags($rotated, true, $secret);
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

    private function endpointUrl(string $path): string
    {
        return rtrim(config()->string('app.url', 'http://localhost'), '/').$path;
    }

    private function generatedSecret(): string
    {
        return bin2hex(random_bytes(24));
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $metadata */
        $metadata = array_filter(
            $value,
            static fn (mixed $_item, mixed $key): bool => is_string($key),
            ARRAY_FILTER_USE_BOTH,
        );

        return $metadata;
    }

    private function normalizedSecret(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function normalizedStatus(mixed $value): string
    {
        $status = is_string($value) ? trim($value) : '';

        return in_array($status, ['active', 'paused'], true) ? $status : 'active';
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $field));
        }

        return trim($value);
    }

    /**
     * @param  IntegrationDefinition  $definition
     * @return IntegrationWebhookConfig
     */
    private function webhookConfigOrFail(array $definition): array
    {
        if (! $definition['supports']['webhooks'] || $definition['webhook'] === null) {
            throw new ConflictHttpException('This integration does not support managed webhooks.');
        }

        return $definition['webhook'];
    }

    private function withCatalogWebhookFlags(
        IntegrationWebhookData $webhook,
        bool $rotateSupported,
        ?string $secretPlaintext = null,
    ): IntegrationWebhookData {
        return new IntegrationWebhookData(
            id: $webhook->id,
            integrationKey: $webhook->integrationKey,
            name: $webhook->name,
            endpointUrl: $webhook->endpointUrl,
            authMode: $webhook->authMode,
            status: $webhook->status,
            secretConfigured: $webhook->secretConfigured,
            rotateSupported: $rotateSupported,
            secretPlaintext: $secretPlaintext,
            metadata: $webhook->metadata,
            secretLastRotatedAt: $webhook->secretLastRotatedAt,
            createdAt: $webhook->createdAt,
            updatedAt: $webhook->updatedAt,
        );
    }
}
