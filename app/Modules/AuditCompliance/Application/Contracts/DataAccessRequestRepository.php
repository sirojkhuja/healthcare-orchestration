<?php

namespace App\Modules\AuditCompliance\Application\Contracts;

use App\Modules\AuditCompliance\Application\Data\DataAccessRequestData;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestSearchCriteria;

interface DataAccessRequestRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): DataAccessRequestData;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function approve(string $tenantId, string $requestId, array $attributes): ?DataAccessRequestData;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function deny(string $tenantId, string $requestId, array $attributes): ?DataAccessRequestData;

    public function findInTenant(string $tenantId, string $requestId): ?DataAccessRequestData;

    /**
     * @return list<DataAccessRequestData>
     */
    public function listForTenant(string $tenantId, DataAccessRequestSearchCriteria $criteria): array;
}
