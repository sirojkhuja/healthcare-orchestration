<?php

namespace App\Modules\Integrations\Application\Queries;

final readonly class ListIntegrationTokensQuery
{
    public function __construct(
        public string $integrationKey,
    ) {}
}
