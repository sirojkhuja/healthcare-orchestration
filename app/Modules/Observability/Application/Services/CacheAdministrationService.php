<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Observability\Application\Data\CacheOperationData;
use App\Shared\Application\Contracts\TenantCache;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CacheAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantCache $tenantCache,
        private readonly FeatureFlagService $featureFlagService,
        private readonly RateLimitService $rateLimitService,
        private readonly RuntimeConfigService $runtimeConfigService,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  list<string>|null  $domains
     */
    public function flush(?array $domains, bool $includeGlobalReferenceData): CacheOperationData
    {
        $resolvedDomains = $this->resolveDomains($domains);
        $tenantId = $this->tenantContext->requireTenantId();
        $namespaceInvalidations = 0;

        foreach ($resolvedDomains as $domain) {
            $this->tenantCache->invalidate($domain, $tenantId);
            $namespaceInvalidations++;

            if ($domain === 'reference-data' && $includeGlobalReferenceData) {
                $this->tenantCache->invalidate($domain, null);
                $namespaceInvalidations++;
            }
        }

        $result = new CacheOperationData(
            action: 'flush',
            domains: $resolvedDomains,
            includeGlobalReferenceData: $includeGlobalReferenceData,
            namespaceInvalidations: $namespaceInvalidations,
            warmed: [],
            performedAt: CarbonImmutable::now(),
        );

        $this->audit('admin.cache_flushed', $result);

        return $result;
    }

    /**
     * @param  list<string>|null  $domains
     */
    public function rebuild(?array $domains, bool $includeGlobalReferenceData): CacheOperationData
    {
        $flushed = $this->flush($domains, $includeGlobalReferenceData);
        $this->featureFlagService->list();
        $this->rateLimitService->list();
        $this->runtimeConfigService->get();

        $result = new CacheOperationData(
            action: 'rebuild',
            domains: $flushed->domains,
            includeGlobalReferenceData: $includeGlobalReferenceData,
            namespaceInvalidations: $flushed->namespaceInvalidations,
            warmed: ['feature-flags', 'rate-limits', 'runtime-config'],
            performedAt: CarbonImmutable::now(),
        );

        $this->audit('admin.caches_rebuilt', $result);

        return $result;
    }

    private function audit(string $action, CacheOperationData $result): void
    {
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $action,
            objectType: 'ops_cache',
            objectId: $this->tenantContext->requireTenantId(),
            after: $result->toArray(),
        ));
    }

    /**
     * @param  list<string>|null  $domains
     * @return list<string>
     */
    private function resolveDomains(?array $domains): array
    {
        $supported = $this->supportedDomains();

        if ($domains === null || $domains === []) {
            return $supported;
        }

        $resolved = [];

        foreach ($domains as $domain) {
            if (! in_array($domain, $supported, true)) {
                throw new UnprocessableEntityHttpException('The cache domain is not supported.');
            }

            $resolved[] = $domain;
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @return list<string>
     */
    private function supportedDomains(): array
    {
        $configured = config('operations.cache.domains', []);

        if (! is_array($configured)) {
            return [];
        }

        $domains = array_values(array_filter(
            $configured,
            static fn (mixed $domain): bool => is_string($domain),
        ));

        /** @var list<string> $domains */

        return $domains;
    }
}
