<?php

namespace App\Modules\Integrations\Application\Queries;

final readonly class IntegrationHealthQuery
{
    public function __construct(
        public string $integrationKey,
    ) {}
}
