<?php

namespace App\Modules\Integrations\Application\Queries;

final readonly class GetIntegrationQuery
{
    public function __construct(
        public string $integrationKey,
    ) {}
}
