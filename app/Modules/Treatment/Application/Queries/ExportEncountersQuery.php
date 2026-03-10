<?php

namespace App\Modules\Treatment\Application\Queries;

use App\Modules\Treatment\Application\Data\EncounterListCriteria;

final readonly class ExportEncountersQuery
{
    public function __construct(
        public EncounterListCriteria $criteria,
        public string $format = 'csv',
    ) {}
}
