<?php

namespace App\Shared\Application\Queries;

final readonly class ListInsuranceCodesQuery
{
    public function __construct(
        public ?string $query = null,
        public int $limit = 25,
    ) {}
}
