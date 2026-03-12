<?php

namespace App\Shared\Application\Queries;

use App\Shared\Application\Data\GlobalSearchCriteria;

final readonly class GlobalSearchQuery
{
    public function __construct(
        public GlobalSearchCriteria $criteria,
    ) {}
}
