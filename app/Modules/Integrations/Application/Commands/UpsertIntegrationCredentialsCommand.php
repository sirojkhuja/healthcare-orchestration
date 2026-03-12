<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class UpsertIntegrationCredentialsCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $integrationKey,
        public array $attributes,
    ) {}
}
