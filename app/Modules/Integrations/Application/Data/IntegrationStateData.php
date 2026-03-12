<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class IntegrationStateData
{
    public function __construct(
        public string $integrationKey,
        public bool $enabled,
        public ?string $lastTestStatus,
        public ?string $lastTestMessage,
        public ?CarbonImmutable $lastTestedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
