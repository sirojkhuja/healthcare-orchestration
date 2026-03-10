<?php

namespace App\Modules\Billing\Application\Queries;

use App\Modules\Billing\Application\Data\PriceListListCriteria;

final readonly class ListPriceListsQuery
{
    public function __construct(public PriceListListCriteria $criteria) {}
}
