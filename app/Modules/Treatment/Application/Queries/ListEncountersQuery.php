<?php

namespace App\Modules\Treatment\Application\Queries;

use App\Modules\Treatment\Application\Data\EncounterListCriteria;

final readonly class ListEncountersQuery
{
    public function __construct(
        public EncounterListCriteria $criteria,
    ) {}
}
