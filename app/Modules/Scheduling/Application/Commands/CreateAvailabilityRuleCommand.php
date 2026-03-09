<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class CreateAvailabilityRuleCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $providerId,
        public array $attributes,
    ) {}
}
