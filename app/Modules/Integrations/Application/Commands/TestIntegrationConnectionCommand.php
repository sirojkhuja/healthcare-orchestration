<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class TestIntegrationConnectionCommand
{
    public function __construct(
        public string $integrationKey,
    ) {}
}
