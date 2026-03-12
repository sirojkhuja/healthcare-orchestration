<?php

namespace App\Modules\Integrations\Application\Queries;

final readonly class ListIntegrationsQuery
{
    public function __construct(
        public ?string $category = null,
        public ?bool $enabled = null,
    ) {}
}
