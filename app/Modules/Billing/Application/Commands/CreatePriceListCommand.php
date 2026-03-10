<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class CreatePriceListCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}
}
