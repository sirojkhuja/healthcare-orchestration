<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Treatment\Application\Contracts\EncounterRepository;
use App\Modules\Treatment\Application\Data\BulkEncounterUpdateData;
use App\Modules\Treatment\Application\Data\EncounterData;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class EncounterBulkUpdateService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EncounterRepository $encounterRepository,
        private readonly EncounterAttributeNormalizer $encounterAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  list<string>  $encounterIds
     * @param  array{
     *     status?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     encountered_at?: string,
     *     timezone?: string
     * }  $changes
     */
    public function update(array $encounterIds, array $changes): BulkEncounterUpdateData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalizedIds = $this->encounterIds($encounterIds);
        $normalizedChanges = $this->sanitizeChanges($changes);
        $operationId = (string) Str::uuid();
        $updatedFields = array_keys($normalizedChanges);
        $mutations = [];

        /** @var list<EncounterData> $encounters */
        $encounters = DB::transaction(function () use (
            $tenantId,
            $normalizedIds,
            $normalizedChanges,
            &$mutations,
        ): array {
            $updated = [];

            foreach ($normalizedIds as $encounterId) {
                $encounter = $this->encounterOrFail($tenantId, $encounterId);
                $updates = $this->encounterAttributeNormalizer->normalizePatch($encounter, $normalizedChanges);

                if ($updates === []) {
                    $updated[] = $encounter;

                    continue;
                }

                $result = $this->encounterRepository->update($tenantId, $encounter->encounterId, $updates);

                if (! $result instanceof EncounterData) {
                    throw new \LogicException('Updated encounter could not be reloaded.');
                }

                $updated[] = $result;
                $mutations[] = [
                    'before' => $encounter,
                    'after' => $result,
                ];
            }

            return $updated;
        });

        if ($mutations !== []) {
            $this->recordAudit($operationId, $updatedFields, $encounters, $mutations);
        }

        return new BulkEncounterUpdateData(
            operationId: $operationId,
            affectedCount: count($encounters),
            updatedFields: $updatedFields,
            encounters: $encounters,
        );
    }

    /**
     * @param  list<string>  $updatedFields
     * @param  list<EncounterData>  $encounters
     * @param  list<array{before: EncounterData, after: EncounterData}>  $mutations
     */
    private function recordAudit(string $operationId, array $updatedFields, array $encounters, array $mutations): void
    {
        $encounterIds = array_map(
            static fn (EncounterData $encounter): string => $encounter->encounterId,
            $encounters,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'encounters.bulk_updated',
            objectType: 'encounter_bulk_operation',
            objectId: $operationId,
            after: [
                'operation_id' => $operationId,
                'encounter_ids' => $encounterIds,
                'updated_fields' => $updatedFields,
                'affected_count' => count($encounters),
            ],
            metadata: [
                'encounter_ids' => $encounterIds,
                'updated_fields' => $updatedFields,
            ],
        ));

        foreach ($mutations as $mutation) {
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'encounters.updated',
                objectType: 'encounter',
                objectId: $mutation['after']->encounterId,
                before: $mutation['before']->toArray(),
                after: $mutation['after']->toArray(),
                metadata: [
                    'source' => 'bulk_update',
                    'bulk_operation_id' => $operationId,
                ],
            ));
        }
    }

    /**
     * @param  list<string>  $encounterIds
     * @return list<string>
     */
    private function encounterIds(array $encounterIds): array
    {
        $normalized = array_values(array_filter(
            $encounterIds,
            static fn (string $encounterId): bool => $encounterId !== '',
        ));

        if ($normalized === []) {
            throw new UnprocessableEntityHttpException('Bulk encounter updates require at least one encounter id.');
        }

        if (count($normalized) > 100) {
            throw new UnprocessableEntityHttpException('Bulk encounter updates may target at most 100 encounters.');
        }

        if (count(array_unique($normalized)) !== count($normalized)) {
            throw new UnprocessableEntityHttpException('Bulk encounter updates require distinct encounter ids.');
        }

        return $normalized;
    }

    private function encounterOrFail(string $tenantId, string $encounterId): EncounterData
    {
        $encounter = $this->encounterRepository->findInTenant($tenantId, $encounterId);

        if (! $encounter instanceof EncounterData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $encounter;
    }

    /**
     * @param  array{
     *     status?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     encountered_at?: string,
     *     timezone?: string
     * }  $changes
     * @return array{
     *     status?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     encountered_at?: string,
     *     timezone?: string
     * }
     */
    private function sanitizeChanges(array $changes): array
    {
        $normalized = [];

        if (array_key_exists('status', $changes)) {
            $normalized['status'] = $changes['status'];
        }

        if (array_key_exists('provider_id', $changes)) {
            $normalized['provider_id'] = $changes['provider_id'];
        }

        if (array_key_exists('clinic_id', $changes)) {
            $normalized['clinic_id'] = $changes['clinic_id'];
        }

        if (array_key_exists('room_id', $changes)) {
            $normalized['room_id'] = $changes['room_id'];
        }

        if (array_key_exists('encountered_at', $changes)) {
            $normalized['encountered_at'] = $changes['encountered_at'];
        }

        if (array_key_exists('timezone', $changes)) {
            $normalized['timezone'] = $changes['timezone'];
        }

        if ($normalized === []) {
            throw new UnprocessableEntityHttpException('Bulk encounter updates require at least one change.');
        }

        return $normalized;
    }
}
