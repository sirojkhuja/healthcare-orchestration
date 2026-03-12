<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class RefreshIntegrationTokensCommand
{
    public function __construct(
        public string $integrationKey,
        public ?string $tokenId = null,
    ) {}
}
