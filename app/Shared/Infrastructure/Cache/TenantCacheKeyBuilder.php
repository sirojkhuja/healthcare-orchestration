<?php

namespace App\Shared\Infrastructure\Cache;

use App\Shared\Application\Contracts\CacheKeyBuilder;

final class TenantCacheKeyBuilder implements CacheKeyBuilder
{
    #[\Override]
    public function itemKey(string $domain, array $segments, ?string $tenantId, int $namespaceVersion): string
    {
        return implode(':', [
            $this->prefix(),
            $this->tenantSegment($tenantId),
            $this->normalize($domain),
            "v{$namespaceVersion}",
            ...array_map($this->normalize(...), $segments),
        ]);
    }

    #[\Override]
    public function namespaceKey(string $domain, ?string $tenantId): string
    {
        return implode(':', [
            $this->prefix(),
            $this->tenantSegment($tenantId),
            $this->normalize($domain),
            'namespace',
        ]);
    }

    private function normalize(int|float|string|bool $segment): string
    {
        return rawurlencode((string) $segment);
    }

    private function tenantSegment(?string $tenantId): string
    {
        return 'tenant:'.($tenantId ?? 'global');
    }

    private function prefix(): string
    {
        /** @psalm-suppress MixedAssignment */
        $configuredPrefix = config('medflow.cache.namespace', 'medflow');

        return is_string($configuredPrefix) && $configuredPrefix !== '' ? $configuredPrefix : 'medflow';
    }
}
