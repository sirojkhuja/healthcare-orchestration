<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class UpdateAvailabilityRuleCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $providerId,
        public string $ruleId,
        public array $attributes,
    ) {}
}
