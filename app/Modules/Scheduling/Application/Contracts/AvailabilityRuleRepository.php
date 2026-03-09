<?php

namespace App\Modules\Scheduling\Application\Contracts;

use App\Modules\Scheduling\Application\Data\AvailabilityRuleData;
use Carbon\CarbonImmutable;

interface AvailabilityRuleRepository
{
    /**
     * @param  array{
     *      scope_type: string,
     *      availability_type: string,
     *      weekday: string|null,
     *      specific_date: CarbonImmutable|null,
     *      start_time: string,
     *      end_time: string,
     *      notes: string|null
     *  }  $attributes
     */
    public function create(string $tenantId, string $providerId, array $attributes): AvailabilityRuleData;

    public function delete(string $tenantId, string $providerId, string $ruleId): bool;

    public function findForProvider(string $tenantId, string $providerId, string $ruleId): ?AvailabilityRuleData;

    public function hasConflict(
        string $tenantId,
        string $providerId,
        string $scopeType,
        string $availabilityType,
        ?string $weekday,
        ?CarbonImmutable $specificDate,
        string $startTime,
        string $endTime,
        ?string $exceptRuleId = null,
    ): bool;

    /**
     * @return list<AvailabilityRuleData>
     */
    public function listForProvider(string $tenantId, string $providerId): array;

    /**
     * @return list<AvailabilityRuleData>
     */
    public function listRelevantForDateRange(
        string $tenantId,
        string $providerId,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo,
    ): array;

    /**
     * @param  array{
     *      scope_type?: string,
     *      availability_type?: string,
     *      weekday?: string|null,
     *      specific_date?: CarbonImmutable|null,
     *      start_time?: string,
     *      end_time?: string,
     *      notes?: string|null
     *  }  $attributes
     */
    public function update(string $tenantId, string $providerId, string $ruleId, array $attributes): ?AvailabilityRuleData;
}
