<?php

namespace App\Modules\Provider\Application\Queries;

final readonly class GetProviderWorkHoursQuery
{
    public function __construct(
        public string $providerId,
    ) {}
}
