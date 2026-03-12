<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class CacheOperationData
{
    /**
     * @param  list<string>  $domains
     * @param  list<string>  $warmed
     */
    public function __construct(
        public string $action,
        public array $domains,
        public bool $includeGlobalReferenceData,
        public int $namespaceInvalidations,
        public array $warmed,
        public CarbonImmutable $performedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'domains' => $this->domains,
            'include_global_reference_data' => $this->includeGlobalReferenceData,
            'namespace_invalidations' => $this->namespaceInvalidations,
            'warmed' => $this->warmed,
            'performed_at' => $this->performedAt->toIso8601String(),
        ];
    }
}
