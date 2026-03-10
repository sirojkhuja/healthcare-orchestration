<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class UpdateInvoiceItemCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $invoiceId,
        public string $itemId,
        public array $attributes,
    ) {}
}
