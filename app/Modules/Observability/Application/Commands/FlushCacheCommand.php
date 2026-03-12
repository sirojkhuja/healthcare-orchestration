<?php

namespace App\Modules\Observability\Application\Commands;

final readonly class FlushCacheCommand
{
    /**
     * @param  list<string>|null  $domains
     */
    public function __construct(
        public ?array $domains,
        public bool $includeGlobalReferenceData,
    ) {}
}
