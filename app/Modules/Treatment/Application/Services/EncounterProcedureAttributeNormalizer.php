<?php

namespace App\Modules\Treatment\Application\Services;

use Carbon\CarbonImmutable;

final class EncounterProcedureAttributeNormalizer
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     treatment_item_id: ?string,
     *     code: ?string,
     *     display_name: string,
     *     performed_at: ?CarbonImmutable,
     *     notes: ?string
     * }
     */
    public function normalizeCreate(array $attributes): array
    {
        return [
            'treatment_item_id' => $this->nullableTrimmedString($attributes['treatment_item_id'] ?? null),
            'code' => $this->nullableTrimmedString($attributes['code'] ?? null),
            'display_name' => $this->requiredTrimmedString($attributes['display_name'] ?? null),
            'performed_at' => array_key_exists('performed_at', $attributes) && is_string($attributes['performed_at'])
                ? CarbonImmutable::parse(trim($attributes['performed_at']))
                : null,
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
        ];
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function requiredTrimmedString(mixed $value): string
    {
        return $this->nullableTrimmedString($value) ?? '';
    }
}
