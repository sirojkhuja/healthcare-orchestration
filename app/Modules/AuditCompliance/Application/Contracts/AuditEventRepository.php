<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use Carbon\CarbonImmutable;

interface AuditEventRepository
{
    public function append(AuditEventData $event): void;

    public function findById(string $eventId): ?AuditEventData;

    /**
     * @return list<AuditEventData>
     */
    public function forObject(string $objectType, string $objectId, ?string $tenantId = null): array;

    /**
     * @return list<AuditEventData>
     */
    public function forActionPrefix(string $actionPrefix, ?string $tenantId = null, int $limit = 50): array;

    public function pruneOlderThan(CarbonImmutable $cutoff): int;
}
