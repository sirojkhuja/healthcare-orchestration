<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class StoredIntegrationCredentialsData
{
    /**
     * @param  array<string, string|null>  $values
     * @param  list<string>  $configuredFields
     */
    public function __construct(
        public string $integrationKey,
        public array $values,
        public array $configuredFields,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}
}
