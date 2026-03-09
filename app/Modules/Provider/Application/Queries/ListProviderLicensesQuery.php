<?php

namespace App\Modules\Provider\Application\Queries;

final readonly class ListProviderLicensesQuery
{
    public function __construct(
        public string $providerId,
    ) {}
}
