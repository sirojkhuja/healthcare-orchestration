<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class CreateInvoiceCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
