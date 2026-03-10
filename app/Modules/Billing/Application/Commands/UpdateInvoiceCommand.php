<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class UpdateInvoiceCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $invoiceId,
        public array $attributes,
    ) {}
}
