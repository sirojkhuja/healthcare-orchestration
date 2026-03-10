<?php

namespace App\Modules\Billing\Application\Queries;

use App\Modules\Billing\Application\Data\BillableServiceListCriteria;

final readonly class ListBillableServicesQuery
{
    public function __construct(public BillableServiceListCriteria $criteria) {}
}
