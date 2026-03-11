<?php

namespace App\Modules\Insurance\Application\Queries;

final readonly class ListPayersQuery
{
    public function __construct(
        public ?string $query = null,
        public ?string $insuranceCode = null,
        public ?bool $isActive = null,
        public int $limit = 25,
    ) {}
}
