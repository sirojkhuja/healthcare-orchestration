<?php

namespace App\Modules\Scheduling\Application\Contracts;

use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AppointmentSearchCriteria;
use Carbon\CarbonImmutable;

interface AppointmentRepository
{
    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     status: string,
     *     scheduled_start_at: CarbonImmutable,
     *     scheduled_end_at: CarbonImmutable,
     *     timezone: string,
     *     last_transition: array<string, mixed>|null
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): AppointmentData;

    public function findInTenant(string $tenantId, string $appointmentId, bool $withDeleted = false): ?AppointmentData;

    /**
     * @return list<AppointmentData>
     */
    public function listForTenant(string $tenantId): array;

    /**
     * @return list<AppointmentData>
     */
    public function search(string $tenantId, AppointmentSearchCriteria $criteria): array;

    public function softDelete(string $tenantId, string $appointmentId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $appointmentId, array $updates): ?AppointmentData;
}
