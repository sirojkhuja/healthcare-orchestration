<?php

namespace App\Modules\Lab\Application\Data;

use Carbon\CarbonImmutable;

final readonly class LabProviderDispatchData
{
    public function __construct(
        public string $externalOrderId,
        public CarbonImmutable $occurredAt,
    ) {}
}
