<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class RevokeIntegrationTokenCommand
{
    public function __construct(
        public string $integrationKey,
        public string $tokenId,
    ) {}
}
