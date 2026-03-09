<?php

namespace App\Modules\Provider\Application\Queries;

final readonly class GetProviderProfileQuery
{
    public function __construct(
        public string $providerId,
    ) {}
}
