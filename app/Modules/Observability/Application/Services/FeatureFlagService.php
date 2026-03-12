<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Observability\Application\Contracts\FeatureFlagRepository;
use App\Modules\Observability\Application\Contracts\FeatureFlagResolver;
use App\Modules\Observability\Application\Data\FeatureFlagData;
use App\Shared\Application\Contracts\TenantCache;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class FeatureFlagService implements FeatureFlagResolver
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly FeatureFlagRepository $featureFlagRepository,
        private readonly TenantCache $tenantCache,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    #[\Override]
    public function isEnabled(string $key): bool
    {
        foreach ($this->list() as $flag) {
            if ($flag->key === $key) {
                return $flag->enabled;
            }
        }

        return false;
    }

    /**
     * @return list<FeatureFlagData>
     */
    public function list(): array
    {
        $tenantId = $this->tenantContext->requireTenantId();

        /** @var list<FeatureFlagData> $flags */
        $flags = $this->tenantCache->remember(
            'feature-flags',
            ['catalog'],
            $tenantId,
            300,
            fn (): array => $this->buildFlags($tenantId),
        );

        return $flags;
    }

    /**
     * @param  array<string, bool>  $flags
     * @return list<FeatureFlagData>
     */
    public function set(array $flags): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $catalog = $this->catalog();

        foreach ($flags as $key => $enabled) {
            if (! array_key_exists($key, $catalog)) {
                throw new UnprocessableEntityHttpException('The feature flag key is not supported.');
            }
        }

        $this->featureFlagRepository->saveMany($tenantId, $flags);
        $this->tenantCache->invalidate('feature-flags', $tenantId);
        $this->tenantCache->invalidate('integrations', $tenantId);

        $resolved = $this->list();
        $after = [];

        foreach ($resolved as $flag) {
            if (! array_key_exists($flag->key, $flags)) {
                continue;
            }

            $after[$flag->key] = $flag->toArray();
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'admin.feature_flags_updated',
            objectType: 'ops_feature_flags',
            objectId: $tenantId,
            after: $after,
            metadata: [
                'feature_flag_keys' => array_keys($after),
            ],
        ));

        return $resolved;
    }

    /**
     * @return list<FeatureFlagData>
     */
    private function buildFlags(string $tenantId): array
    {
        $overrides = $this->featureFlagRepository->listForTenant($tenantId);
        $items = [];

        foreach ($this->catalog() as $key => $metadata) {
            $defaultEnabled = (bool) config('integrations.feature_flags.'.$key, false);
            $override = $overrides[$key] ?? null;

            $items[] = new FeatureFlagData(
                key: $key,
                name: $metadata['name'],
                description: $metadata['description'],
                module: $metadata['module'],
                enabled: $override['enabled'] ?? $defaultEnabled,
                defaultEnabled: $defaultEnabled,
                source: $override !== null ? 'tenant_override' : 'default',
                updatedAt: $override['updated_at'] ?? null,
            );
        }

        usort($items, static fn (FeatureFlagData $left, FeatureFlagData $right): int => $left->key <=> $right->key);

        return $items;
    }

    /**
     * @return array<string, array{name: string, description: string, module: string}>
     */
    private function catalog(): array
    {
        $configured = config('operations.feature_flags', []);

        if (! is_array($configured)) {
            return [];
        }

        $catalog = [];

        foreach ($configured as $key => $metadata) {
            if (! is_string($key) || ! is_array($metadata)) {
                continue;
            }

            $name = $metadata['name'] ?? null;
            $description = $metadata['description'] ?? null;
            $module = $metadata['module'] ?? null;

            if (! is_string($name) || ! is_string($description) || ! is_string($module)) {
                continue;
            }

            $catalog[$key] = [
                'name' => $name,
                'description' => $description,
                'module' => $module,
            ];
        }

        return $catalog;
    }
}
