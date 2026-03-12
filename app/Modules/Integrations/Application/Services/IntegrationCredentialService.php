<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Integrations\Application\Contracts\IntegrationCatalog;
use App\Modules\Integrations\Application\Contracts\IntegrationCredentialRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationLogRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationTokenRepository;
use App\Modules\Integrations\Application\Data\IntegrationCredentialViewData;
use App\Modules\Integrations\Application\Data\StoredIntegrationCredentialsData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @phpstan-import-type IntegrationDefinition from IntegrationCatalog
 */
final class IntegrationCredentialService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IntegrationCatalog $integrationCatalog,
        private readonly IntegrationCredentialRepository $integrationCredentialRepository,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly IntegrationLogRepository $integrationLogRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function delete(string $integrationKey): IntegrationCredentialViewData
    {
        $definition = $this->definitionOrFail($integrationKey);

        if (! $this->supportsCredentials($definition)) {
            throw new ConflictHttpException('This integration does not support managed credentials.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $existing = $this->integrationCredentialRepository->get($tenantId, $integrationKey);
        $now = CarbonImmutable::now();
        $deleted = $this->integrationCredentialRepository->delete($tenantId, $integrationKey);
        $revokedCount = $this->integrationTokenRepository->revokeAll($tenantId, $integrationKey, $now);

        if ($deleted || $revokedCount > 0) {
            $configuredFields = $existing instanceof StoredIntegrationCredentialsData ? $existing->configuredFields : [];

            $this->integrationLogRepository->create(
                $tenantId,
                $integrationKey,
                'warning',
                'integration.credentials_deleted',
                'Managed integration credentials deleted.',
                [
                    'revoked_token_count' => $revokedCount,
                ],
                $now,
            );
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'integrations.credentials_deleted',
                objectType: 'integration',
                objectId: $integrationKey,
                before: [
                    'configured_fields' => $configuredFields,
                ],
                metadata: [
                    'integration_key' => $integrationKey,
                    'revoked_token_count' => $revokedCount,
                ],
            ));
        }

        return $this->get($integrationKey);
    }

    public function get(string $integrationKey): IntegrationCredentialViewData
    {
        $definition = $this->definitionOrFail($integrationKey);
        $stored = $this->integrationCredentialRepository->get($this->tenantContext->requireTenantId(), $integrationKey);

        return $this->toView($definition, $stored);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(string $integrationKey, array $attributes): IntegrationCredentialViewData
    {
        $definition = $this->definitionOrFail($integrationKey);

        if (! $this->supportsCredentials($definition)) {
            throw new ConflictHttpException('This integration does not support managed credentials.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->normalizeValues($definition, $attributes);
        $now = CarbonImmutable::now();
        $stored = $this->integrationCredentialRepository->save(
            $tenantId,
            $integrationKey,
            $normalized['values'],
            $normalized['configured_fields'],
            $now,
        );

        if ($definition['supports']['tokens']) {
            $this->integrationTokenRepository->materializeFromCredentials($tenantId, $integrationKey, $normalized['values'], $now);
        }

        $this->integrationLogRepository->create(
            $tenantId,
            $integrationKey,
            'info',
            'integration.credentials_updated',
            'Managed integration credentials updated.',
            [
                'configured_fields' => $stored->configuredFields,
            ],
            $now,
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.credentials_updated',
            objectType: 'integration',
            objectId: $integrationKey,
            after: [
                'configured_fields' => $stored->configuredFields,
            ],
            metadata: [
                'integration_key' => $integrationKey,
            ],
        ));

        return $this->toView($definition, $stored);
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

    private function maskValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return '****'.substr($value, -4);
    }

    /**
     * @param  IntegrationDefinition  $definition
     * @param  array<string, mixed>  $attributes
     * @return array{
     *   values: array<string, string|null>,
     *   configured_fields: list<string>
     * }
     */
    private function normalizeValues(array $definition, array $attributes): array
    {
        $input = $attributes['values'] ?? null;

        if (! is_array($input)) {
            throw new UnprocessableEntityHttpException('The values field is required.');
        }

        $fields = $definition['credential_fields'];
        $values = [];
        $configuredFields = [];

        foreach ($fields as $field) {
            $key = $field['key'];

            if (! array_key_exists($key, $input)) {
                $values[$key] = null;

                continue;
            }

            $value = $input[$key];

            if ($value !== null && ! is_scalar($value)) {
                throw new UnprocessableEntityHttpException(sprintf('The %s credential value must be a scalar string or null.', $key));
            }

            $normalized = $value === null ? null : trim((string) $value);
            $values[$key] = $normalized !== '' ? $normalized : null;

            if ($values[$key] !== null) {
                $configuredFields[] = $key;
            }
        }

        foreach (array_keys($input) as $key) {
            if (! is_string($key) || ! array_key_exists($key, $values)) {
                throw new UnprocessableEntityHttpException(sprintf('The %s credential field is not supported by this integration.', (string) $key));
            }
        }

        foreach ($fields as $field) {
            if (! $field['required']) {
                continue;
            }

            $key = $field['key'];

            if (($values[$key] ?? null) === null) {
                throw new UnprocessableEntityHttpException(sprintf('The %s credential field is required.', $key));
            }
        }

        return [
            'values' => $values,
            'configured_fields' => array_values(array_unique($configuredFields)),
        ];
    }

    /**
     * @param  IntegrationDefinition  $definition
     */
    private function supportsCredentials(array $definition): bool
    {
        return $definition['supports']['credentials'];
    }

    /**
     * @param  IntegrationDefinition  $definition
     */
    private function toView(array $definition, ?StoredIntegrationCredentialsData $stored): IntegrationCredentialViewData
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = [];
        /** @var array<string, string|null> $values */
        $values = [];
        $configuredFields = $stored instanceof StoredIntegrationCredentialsData ? $stored->configuredFields : [];

        foreach ($definition['credential_fields'] as $field) {
            $key = $field['key'];
            $secret = $field['secret'];
            $value = $stored?->values[$key] ?? null;
            $values[$key] = $secret ? $this->maskValue($value) : $value;
            $fields[] = [
                'key' => $key,
                'label' => $field['label'],
                'secret' => $secret,
                'required' => $field['required'],
                'configured' => in_array($key, $configuredFields, true),
            ];
        }

        return new IntegrationCredentialViewData(
            integrationKey: $definition['integration_key'],
            source: $stored instanceof StoredIntegrationCredentialsData ? 'tenant' : 'none',
            supportsCredentials: $this->supportsCredentials($definition),
            configured: $stored instanceof StoredIntegrationCredentialsData && $stored->configuredFields !== [],
            fields: $fields,
            values: $values,
            updatedAt: $stored?->updatedAt,
        );
    }
}
