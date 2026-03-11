<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\SmsRoutingRuleData;

interface SmsRoutingRepository
{
    public function findForTenantAndMessageType(string $tenantId, string $messageType): ?SmsRoutingRuleData;

    /**
     * @return list<SmsRoutingRuleData>
     */
    public function listForTenant(string $tenantId): array;

    /**
     * @param  list<string>  $providers
     */
    public function upsert(string $tenantId, string $messageType, array $providers): SmsRoutingRuleData;
}
