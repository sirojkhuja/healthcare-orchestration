<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class BulkRescheduleAppointmentsCommand
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
