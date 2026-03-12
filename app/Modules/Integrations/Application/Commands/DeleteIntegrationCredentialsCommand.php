<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class DeleteIntegrationCredentialsCommand
{
    public function __construct(
        public string $integrationKey,
    ) {}
}
