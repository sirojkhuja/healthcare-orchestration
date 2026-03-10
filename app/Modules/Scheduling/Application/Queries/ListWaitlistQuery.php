<?php

namespace App\Modules\Scheduling\Application\Queries;

final readonly class ListWaitlistQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public array $filters = [],
    ) {}
}
