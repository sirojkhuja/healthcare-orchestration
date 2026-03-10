<?php

namespace App\Modules\Billing\Application\Queries;

final readonly class GetPriceListQuery
{
    public function __construct(public string $priceListId) {}
}
