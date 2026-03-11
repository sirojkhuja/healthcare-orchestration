<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Application\Data\SmsRoutingRuleData;
use stdClass;

final class SmsRoutingRuleRecordMapper
{
    public function toData(stdClass $row): SmsRoutingRuleData
    {
        return new SmsRoutingRuleData(
            tenantId: $this->stringValue($row->tenant_id ?? null),
            messageType: $this->stringValue($row->message_type ?? null),
            providers: $this->providers($row->providers ?? null),
        );
    }

    /**
     * @return list<string>
     */
    private function providers(mixed $value): array
    {
        if (is_string($value) && trim($value) !== '') {
            /** @var mixed $decoded */
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return [];
            }

            return $this->providersFromArray($decoded);
        }

        return is_array($value) ? $this->providersFromArray($value) : [];
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return list<string>
     */
    private function providersFromArray(array $value): array
    {
        $providers = [];

        /** @var mixed $provider */
        foreach ($value as $provider) {
            if (is_string($provider) && trim($provider) !== '') {
                $providers[] = trim($provider);
            }
        }

        return array_values(array_unique($providers));
    }

    private function stringValue(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
