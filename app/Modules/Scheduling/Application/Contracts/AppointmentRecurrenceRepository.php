<?php

namespace App\Modules\Scheduling\Application\Contracts;

use App\Modules\Scheduling\Application\Data\AppointmentRecurrenceData;

interface AppointmentRecurrenceRepository
{
    /**
     * @param  array{
     *     source_appointment_id: string,
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     frequency: string,
     *     interval: int,
     *     occurrence_count: ?int,
     *     until_date: ?string,
     *     timezone: string,
     *     status: string,
     *     canceled_reason: ?string
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): AppointmentRecurrenceData;

    public function findInTenant(string $tenantId, string $recurrenceId): ?AppointmentRecurrenceData;

    /**
     * @param  array{status?: string, canceled_reason?: ?string}  $updates
     */
    public function update(string $tenantId, string $recurrenceId, array $updates): ?AppointmentRecurrenceData;
}
