<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\PiiFieldData;
use App\Modules\AuditCompliance\Application\Data\PiiFieldMutationData;
use Carbon\CarbonImmutable;

interface PiiFieldRepository
{
    /**
     * @return list<PiiFieldData>
     */
    public function listForTenant(string $tenantId): array;

    /**
     * @param  list<string>  $fieldIds
     * @return list<PiiFieldData>
     */
    public function findActiveByIds(string $tenantId, array $fieldIds): array;

    /**
     * @param  list<PiiFieldMutationData>  $fields
     * @return list<PiiFieldData>
     */
    public function replace(string $tenantId, array $fields, CarbonImmutable $now): array;

    /**
     * @param  list<string>  $fieldIds
     * @return list<PiiFieldData>
     */
    public function rotateKeys(string $tenantId, array $fieldIds, CarbonImmutable $now): array;

    /**
     * @param  list<string>  $fieldIds
     * @return list<PiiFieldData>
     */
    public function markReencrypted(string $tenantId, array $fieldIds, CarbonImmutable $now): array;
}
