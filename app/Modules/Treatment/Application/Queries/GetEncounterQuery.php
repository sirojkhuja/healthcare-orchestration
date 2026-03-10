<?php

namespace App\Modules\Treatment\Application\Queries;

final readonly class GetEncounterQuery
{
    public function __construct(
        public string $encounterId,
    ) {}
}
