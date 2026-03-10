<?php

namespace App\Modules\Lab\Application\Queries;

final readonly class ListLabResultsQuery
{
    public function __construct(
        public string $orderId,
    ) {}
}
