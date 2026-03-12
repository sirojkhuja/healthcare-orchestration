<?php

namespace App\Modules\Provider\Application\Queries;

use App\Modules\Provider\Application\Data\ProviderSearchCriteria;

final readonly class SearchProvidersQuery
{
    public function __construct(
        public ProviderSearchCriteria $criteria,
    ) {}
}
