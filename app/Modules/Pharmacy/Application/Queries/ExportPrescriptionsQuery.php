<?php

namespace App\Modules\Pharmacy\Application\Queries;

use App\Modules\Pharmacy\Application\Data\PrescriptionSearchCriteria;

final readonly class ExportPrescriptionsQuery
{
    public function __construct(
        public PrescriptionSearchCriteria $criteria,
        public string $format,
    ) {}
}
