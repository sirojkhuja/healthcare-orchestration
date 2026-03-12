<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class EnableIntegrationCommand
{
    public function __construct(
        public string $integrationKey,
    ) {}
}
