<?php

namespace App\Modules\Integrations\Application\Queries;

final readonly class ListIntegrationLogsQuery
{
    public function __construct(
        public string $integrationKey,
        public ?string $level = null,
        public ?string $event = null,
        public int $limit = 50,
    ) {}
}
