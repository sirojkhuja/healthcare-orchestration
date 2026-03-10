<?php

namespace App\Modules\Treatment\Application\Queries;

final readonly class ListProceduresQuery
{
    public function __construct(
        public string $encounterId,
    ) {}
}
