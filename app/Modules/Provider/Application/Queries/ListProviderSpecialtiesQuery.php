<?php

namespace App\Modules\Provider\Application\Queries;

final readonly class ListProviderSpecialtiesQuery
{
    public function __construct(
        public string $providerId,
    ) {}
}
