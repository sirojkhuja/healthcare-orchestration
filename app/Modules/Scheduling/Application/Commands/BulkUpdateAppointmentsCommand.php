<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class BulkUpdateAppointmentsCommand
{
    /**
     * @param  list<string>  $appointmentIds
     * @param  array{
     *     patient_id?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     scheduled_start_at?: string,
     *     scheduled_end_at?: string,
     *     timezone?: string
     * }  $changes
     */
    public function __construct(
        public array $appointmentIds,
        public array $changes,
    ) {}
}
