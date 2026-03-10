<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class BulkCancelAppointmentsCommand
{
    /**
     * @param  list<string>  $appointmentIds
     */
    public function __construct(
        public array $appointmentIds,
        public string $reason,
    ) {}
}
