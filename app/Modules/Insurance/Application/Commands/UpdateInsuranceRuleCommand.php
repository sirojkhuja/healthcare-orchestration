<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class UpdateInsuranceRuleCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $ruleId,
        public array $attributes,
    ) {}
}
