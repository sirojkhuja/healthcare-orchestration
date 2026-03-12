<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Observability\Application\Contracts\RateLimitRepository;
use App\Modules\Observability\Application\Data\RateLimitData;
use App\Shared\Application\Contracts\TenantCache;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class RateLimitService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RateLimitRepository $rateLimitRepository,
        private readonly TenantCache $tenantCache,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @return list<RateLimitData>
     */
    public function list(): array
    {
        $tenantId = $this->tenantContext->requireTenantId();

        /** @var list<RateLimitData> $limits */
        $limits = $this->tenantCache->remember(
            'rate-limits',
            ['catalog'],
            $tenantId,
            300,
            fn (): array => $this->buildLimits($tenantId),
        );

        return $limits;
    }

    /**
     * @param  array<string, array{requests_per_minute: int, burst: int}>  $limits
     * @return list<RateLimitData>
     */
    public function update(array $limits): array
    {
        $catalog = $this->catalog();
        $tenantId = $this->tenantContext->requireTenantId();

        foreach ($limits as $bucketKey => $limit) {
            if (! array_key_exists($bucketKey, $catalog)) {
                throw new UnprocessableEntityHttpException('The rate-limit bucket is not supported.');
            }
        }

        $this->rateLimitRepository->saveMany($tenantId, $limits);
        $this->tenantCache->invalidate('rate-limits', $tenantId);
        $resolved = $this->list();
        $after = [];

        foreach ($resolved as $limit) {
            if (! array_key_exists($limit->bucketKey, $limits)) {
                continue;
            }

            $after[$limit->bucketKey] = $limit->toArray();
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'admin.rate_limits_updated',
            objectType: 'ops_rate_limits',
            objectId: $tenantId,
            after: $after,
            metadata: [
                'bucket_keys' => array_keys($after),
            ],
        ));

        return $resolved;
    }

    /**
     * @return list<RateLimitData>
     */
    private function buildLimits(string $tenantId): array
    {
        $overrides = $this->rateLimitRepository->listForTenant($tenantId);
        $items = [];

        foreach ($this->catalog() as $bucketKey => $metadata) {
            $override = $overrides[$bucketKey] ?? null;

            $items[] = new RateLimitData(
                bucketKey: $bucketKey,
                name: $metadata['name'],
                description: $metadata['description'],
                requestsPerMinute: $override['requests_per_minute'] ?? $metadata['requests_per_minute'],
                burst: $override['burst'] ?? $metadata['burst'],
                source: $override !== null ? 'tenant_override' : 'default',
                updatedAt: $override['updated_at'] ?? null,
            );
        }

        usort($items, static fn (RateLimitData $left, RateLimitData $right): int => $left->bucketKey <=> $right->bucketKey);

        return $items;
    }

    /**
     * @return array<string, array{name: string, description: string, requests_per_minute: int, burst: int}>
     */
    private function catalog(): array
    {
        $configured = config('operations.rate_limits', []);

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
            $requestsPerMinute = $metadata['requests_per_minute'] ?? null;
            $burst = $metadata['burst'] ?? null;

            if (! is_string($name) || ! is_string($description) || ! is_int($requestsPerMinute) || ! is_int($burst)) {
                continue;
            }

            $catalog[$key] = [
                'name' => $name,
                'description' => $description,
                'requests_per_minute' => $requestsPerMinute,
                'burst' => $burst,
            ];
        }

        return $catalog;
    }
}
