<?php

namespace App\Modules\Treatment\Application\Contracts;

use App\Modules\Treatment\Application\Data\EncounterProcedureData;
use Carbon\CarbonImmutable;

interface EncounterProcedureRepository
{
    /**
     * @param  array{
     *     treatment_item_id: ?string,
     *     code: ?string,
     *     display_name: string,
     *     performed_at: ?CarbonImmutable,
     *     notes: ?string
     * }  $attributes
     */
    public function create(string $tenantId, string $encounterId, array $attributes): EncounterProcedureData;

    public function delete(string $tenantId, string $encounterId, string $procedureId): bool;

    public function duplicateExists(
        string $tenantId,
        string $encounterId,
        ?string $code,
        string $displayName,
        ?CarbonImmutable $performedAt,
        ?string $treatmentItemId,
    ): bool;

    public function findInEncounter(string $tenantId, string $encounterId, string $procedureId): ?EncounterProcedureData;

    /**
     * @return list<EncounterProcedureData>
     */
    public function listForEncounter(string $tenantId, string $encounterId): array;
}
