<?php

namespace App\Modules\Pharmacy\Application\Contracts;

use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Data\PrescriptionSearchCriteria;
use Carbon\CarbonImmutable;

interface PrescriptionRepository
{
    /**
     * @param  array{
     *     patient_id: string,
     *     provider_id: string,
     *     encounter_id: ?string,
     *     treatment_item_id: ?string,
     *     medication_name: string,
     *     medication_code: ?string,
     *     dosage: string,
     *     route: string,
     *     frequency: string,
     *     quantity: string,
     *     quantity_unit: ?string,
     *     authorized_refills: int,
     *     instructions: ?string,
     *     notes: ?string,
     *     starts_on: ?string,
     *     ends_on: ?string,
     *     status: string,
     *     issued_at: ?CarbonImmutable,
     *     dispensed_at: ?CarbonImmutable,
     *     canceled_at: ?CarbonImmutable,
     *     cancel_reason: ?string,
     *     last_transition: array<string, mixed>|null
     * }  $attributes
     */
    public function create(string $tenantId, array $attributes): PrescriptionData;

    public function findInTenant(string $tenantId, string $prescriptionId, bool $withDeleted = false): ?PrescriptionData;

    /**
     * @return list<PrescriptionData>
     */
    public function search(string $tenantId, PrescriptionSearchCriteria $criteria): array;

    public function softDelete(string $tenantId, string $prescriptionId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $prescriptionId, array $updates): ?PrescriptionData;
}
