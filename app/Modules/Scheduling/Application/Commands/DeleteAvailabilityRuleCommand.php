<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class DeleteAvailabilityRuleCommand
{
    public function __construct(
        public string $providerId,
        public string $ruleId,
    ) {}
}
