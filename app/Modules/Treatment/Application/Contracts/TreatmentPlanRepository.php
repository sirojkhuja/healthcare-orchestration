<?php

namespace App\Modules\Treatment\Application\Contracts;

use App\Modules\Treatment\Application\Data\TreatmentPlanData;
use App\Modules\Treatment\Application\Data\TreatmentPlanSearchCriteria;
use Carbon\CarbonImmutable;

interface TreatmentPlanRepository
{
    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     title: string,
     *     summary: ?string,
     *     goals: ?string,
     *     planned_start_date: ?string,
     *     planned_end_date: ?string,
     *     status: string,
     *     last_transition: array<string, mixed>|null,
     *     approved_at: ?string,
     *     started_at: ?string,
     *     paused_at: ?string,
     *     finished_at: ?string,
     *     rejected_at: ?string
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): TreatmentPlanData;

    public function findInTenant(string $tenantId, string $planId, bool $withDeleted = false): ?TreatmentPlanData;

    /**
     * @return list<TreatmentPlanData>
     */
    public function listForTenant(string $tenantId): array;

    /**
     * @return list<TreatmentPlanData>
     */
    public function search(string $tenantId, TreatmentPlanSearchCriteria $criteria): array;

    public function softDelete(string $tenantId, string $planId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $planId, array $updates): ?TreatmentPlanData;
}
