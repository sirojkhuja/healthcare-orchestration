<?php

namespace App\Modules\Insurance\Application\Queries;

final readonly class ListInsuranceRulesQuery
{
    public function __construct(
        public ?string $query = null,
        public ?string $payerId = null,
        public ?string $serviceCategory = null,
        public ?bool $isActive = null,
        public int $limit = 25,
    ) {}
}
