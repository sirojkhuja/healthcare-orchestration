<?php

namespace App\Modules\Insurance\Application\Queries;

use App\Modules\Insurance\Application\Data\ClaimSearchCriteria;

final readonly class ExportClaimsQuery
{
    public function __construct(
        public ClaimSearchCriteria $criteria,
        public string $format,
    ) {}
}
