<?php

namespace App\Modules\Observability\Application\Queries;

use App\Modules\Observability\Application\Data\OutboxSearchCriteria;

final readonly class ListOutboxQuery
{
    public function __construct(public OutboxSearchCriteria $criteria) {}
}
