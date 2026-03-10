<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class UpdateLabOrderCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $orderId,
        public array $attributes,
    ) {}
}
