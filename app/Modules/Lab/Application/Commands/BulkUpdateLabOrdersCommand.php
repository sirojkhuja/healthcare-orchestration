<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class BulkUpdateLabOrdersCommand
{
    /**
     * @param  list<string>  $orderIds
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public array $orderIds,
        public array $changes,
    ) {}
}
