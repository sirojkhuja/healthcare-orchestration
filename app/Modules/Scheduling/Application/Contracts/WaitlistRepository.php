<?php

namespace App\Modules\Scheduling\Application\Contracts;

use App\Modules\Scheduling\Application\Data\WaitlistEntryData;

interface WaitlistRepository
{
    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     desired_date_from: string,
     *     desired_date_to: string,
     *     preferred_start_time: ?string,
     *     preferred_end_time: ?string,
     *     notes: ?string,
     *     status: string,
     *     booked_appointment_id: ?string,
     *     offered_slot: array<string, mixed>|null
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): WaitlistEntryData;

    public function findInTenant(string $tenantId, string $entryId): ?WaitlistEntryData;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<WaitlistEntryData>
     */
    public function listForTenant(string $tenantId, array $filters = []): array;

    /**
     * @param  array{status?: string, booked_appointment_id?: ?string, offered_slot?: array<string, mixed>|null}  $updates
     */
    public function update(string $tenantId, string $entryId, array $updates): ?WaitlistEntryData;
}
