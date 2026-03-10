<?php

namespace App\Modules\Scheduling\Application\Contracts;

use App\Modules\Scheduling\Application\Data\AppointmentParticipantData;

interface AppointmentParticipantRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, string $appointmentId, array $attributes): AppointmentParticipantData;

    public function delete(string $tenantId, string $appointmentId, string $participantId): bool;

    public function findInTenant(string $tenantId, string $appointmentId, string $participantId): ?AppointmentParticipantData;

    /**
     * @return list<AppointmentParticipantData>
     */
    public function listForAppointment(string $tenantId, string $appointmentId): array;
}
