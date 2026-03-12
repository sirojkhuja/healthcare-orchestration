<?php

namespace App\Shared\Application\Queries;

final readonly class ListCountriesQuery
{
    public function __construct(
        public ?string $query = null,
        public int $limit = 25,
    ) {}
}
