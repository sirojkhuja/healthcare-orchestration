<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class DisableIntegrationCommand
{
    public function __construct(
        public string $integrationKey,
    ) {}
}
