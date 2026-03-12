<?php

namespace App\Modules\Integrations\Application\Data;

use Carbon\CarbonImmutable;

final readonly class IntegrationCredentialViewData
{
    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, string|null>  $values
     */
    public function __construct(
        public string $integrationKey,
        public string $source,
        public bool $supportsCredentials,
        public bool $configured,
        public array $fields,
        public array $values,
        public ?CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'integration_key' => $this->integrationKey,
            'source' => $this->source,
            'supports_credentials' => $this->supportsCredentials,
            'configured' => $this->configured,
            'fields' => $this->fields,
            'values' => $this->values,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
