<?php

namespace App\Modules\Treatment\Application\Queries;

final readonly class ListTreatmentItemsQuery
{
    public function __construct(
        public string $planId,
    ) {}
}
