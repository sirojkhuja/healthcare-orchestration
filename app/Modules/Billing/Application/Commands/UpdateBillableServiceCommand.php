<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class UpdateBillableServiceCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $serviceId,
        public array $attributes,
    ) {}
}
