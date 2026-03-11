<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Application\Contracts\SmsRoutingRepository;
use App\Modules\Notifications\Application\Data\SmsRoutingRuleData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseSmsRoutingRepository implements SmsRoutingRepository
{
    public function __construct(
        private readonly SmsRoutingRuleRecordMapper $smsRoutingRuleRecordMapper,
    ) {}

    #[\Override]
    public function findForTenantAndMessageType(string $tenantId, string $messageType): ?SmsRoutingRuleData
    {
        $row = DB::table('notification_sms_routing_rules')
            ->where('tenant_id', $tenantId)
            ->where('message_type', $messageType)
            ->first();

        return $row instanceof stdClass ? $this->smsRoutingRuleRecordMapper->toData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('notification_sms_routing_rules')
            ->where('tenant_id', $tenantId)
            ->orderBy('message_type')
            ->get()
            ->all();

        return array_map(
            fn (stdClass $row): SmsRoutingRuleData => $this->smsRoutingRuleRecordMapper->toData($row),
            $rows,
        );
    }

    #[\Override]
    public function upsert(string $tenantId, string $messageType, array $providers): SmsRoutingRuleData
    {
        $now = CarbonImmutable::now();
        $existing = DB::table('notification_sms_routing_rules')
            ->where('tenant_id', $tenantId)
            ->where('message_type', $messageType)
            ->first(['id', 'created_at']);

        DB::table('notification_sms_routing_rules')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'message_type' => $messageType,
            ],
            [
                'id' => is_object($existing) && is_string($existing->id ?? null) && $existing->id !== ''
                    ? $existing->id
                    : (string) Str::uuid(),
                'providers' => json_encode($providers, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
                'created_at' => is_object($existing) && isset($existing->created_at)
                    ? $existing->created_at
                    : $now,
            ],
        );

        return $this->findForTenantAndMessageType($tenantId, $messageType)
            ?? throw new \LogicException('SMS routing rule could not be reloaded after upsert.');
    }
}
