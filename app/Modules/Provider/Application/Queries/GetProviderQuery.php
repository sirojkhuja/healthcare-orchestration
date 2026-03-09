<?php

namespace App\Modules\Provider\Application\Queries;

final readonly class GetProviderQuery
{
    public function __construct(
        public string $providerId,
    ) {}
}
