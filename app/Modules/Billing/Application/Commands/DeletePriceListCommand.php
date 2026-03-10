<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class DeletePriceListCommand
{
    public function __construct(public string $priceListId) {}
}
