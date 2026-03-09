<?php

namespace App\Modules\Provider\Application\Queries;

final readonly class ListProviderTimeOffQuery
{
    public function __construct(
        public string $providerId,
    ) {}
}
