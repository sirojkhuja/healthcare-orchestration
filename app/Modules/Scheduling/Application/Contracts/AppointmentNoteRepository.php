<?php

namespace App\Modules\Scheduling\Application\Contracts;

use App\Modules\Scheduling\Application\Data\AppointmentNoteData;

interface AppointmentNoteRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, string $appointmentId, array $attributes): AppointmentNoteData;

    public function delete(string $tenantId, string $appointmentId, string $noteId): bool;

    public function findInTenant(string $tenantId, string $appointmentId, string $noteId): ?AppointmentNoteData;

    /**
     * @return list<AppointmentNoteData>
     */
    public function listForAppointment(string $tenantId, string $appointmentId): array;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $appointmentId, string $noteId, array $updates): ?AppointmentNoteData;
}
