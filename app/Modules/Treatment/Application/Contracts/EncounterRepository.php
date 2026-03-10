<?php

namespace App\Modules\Treatment\Application\Contracts;

use App\Modules\Treatment\Application\Data\EncounterData;
use App\Modules\Treatment\Application\Data\EncounterListCriteria;
use Carbon\CarbonImmutable;

interface EncounterRepository
{
    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     treatment_plan_id: ?string,
     *     appointment_id: ?string,
     *     clinic_id: ?string,
     *     room_id: ?string,
     *     status: string,
     *     encountered_at: CarbonImmutable,
     *     timezone: string,
     *     chief_complaint: ?string,
     *     summary: ?string,
     *     notes: ?string,
     *     follow_up_instructions: ?string
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): EncounterData;

    public function findInTenant(string $tenantId, string $encounterId, bool $withDeleted = false): ?EncounterData;

    /**
     * @return list<EncounterData>
     */
    public function listForTenant(string $tenantId, EncounterListCriteria $criteria): array;

    public function softDelete(string $tenantId, string $encounterId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $encounterId, array $updates): ?EncounterData;
}
