<?php

namespace App\Modules\Integrations\Application\Queries;

final readonly class GetIntegrationCredentialsQuery
{
    public function __construct(
        public string $integrationKey,
    ) {}
}
