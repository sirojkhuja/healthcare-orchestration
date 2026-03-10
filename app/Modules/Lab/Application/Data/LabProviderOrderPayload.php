<?php

namespace App\Modules\Lab\Application\Data;

use Carbon\CarbonImmutable;

final readonly class LabProviderOrderPayload
{
    /**
     * @param  list<LabProviderResultPayload>  $results
     */
    public function __construct(
        public string $externalOrderId,
        public string $status,
        public CarbonImmutable $occurredAt,
        public array $results = [],
    ) {}
}
