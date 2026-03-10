<?php

namespace App\Modules\Treatment\Application\Queries;

final readonly class ListDiagnosesQuery
{
    public function __construct(
        public string $encounterId,
    ) {}
}
