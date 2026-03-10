<?php

namespace App\Modules\Lab\Application\Queries;

use App\Modules\Lab\Application\Data\LabOrderSearchCriteria;

final readonly class ExportLabOrdersQuery
{
    public function __construct(
        public LabOrderSearchCriteria $criteria,
        public string $format,
    ) {}
}
