<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class UpdatePriceListCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $priceListId,
        public array $attributes,
    ) {}
}
