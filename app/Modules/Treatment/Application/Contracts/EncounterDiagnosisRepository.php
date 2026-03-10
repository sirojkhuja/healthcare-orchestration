<?php

namespace App\Modules\Treatment\Application\Contracts;

use App\Modules\Treatment\Application\Data\EncounterDiagnosisData;

interface EncounterDiagnosisRepository
{
    /**
     * @param  array{
     *     code: ?string,
     *     display_name: string,
     *     diagnosis_type: string,
     *     notes: ?string
     * }  $attributes
     */
    public function create(string $tenantId, string $encounterId, array $attributes): EncounterDiagnosisData;

    public function delete(string $tenantId, string $encounterId, string $diagnosisId): bool;

    public function duplicateExists(
        string $tenantId,
        string $encounterId,
        ?string $code,
        string $displayName,
        string $diagnosisType,
    ): bool;

    public function findInEncounter(string $tenantId, string $encounterId, string $diagnosisId): ?EncounterDiagnosisData;

    /**
     * @return list<EncounterDiagnosisData>
     */
    public function listForEncounter(string $tenantId, string $encounterId): array;

    public function primaryDiagnosisExists(string $tenantId, string $encounterId): bool;
}
