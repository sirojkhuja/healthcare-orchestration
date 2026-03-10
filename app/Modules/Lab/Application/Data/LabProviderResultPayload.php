<?php

namespace App\Modules\Lab\Application\Data;

use Carbon\CarbonImmutable;

final readonly class LabProviderResultPayload
{
    /**
     * @param  array<string, mixed>|null  $valueJson
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public ?string $externalResultId,
        public string $status,
        public CarbonImmutable $observedAt,
        public CarbonImmutable $receivedAt,
        public string $valueType,
        public ?string $valueNumeric,
        public ?string $valueText,
        public ?bool $valueBoolean,
        public ?array $valueJson,
        public ?string $unit,
        public ?string $referenceRange,
        public ?string $abnormalFlag,
        public ?string $notes,
        public ?array $rawPayload,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'external_result_id' => $this->externalResultId,
            'status' => $this->status,
            'observed_at' => $this->observedAt->toIso8601String(),
            'received_at' => $this->receivedAt->toIso8601String(),
            'value_type' => $this->valueType,
            'value_numeric' => $this->valueNumeric,
            'value_text' => $this->valueText,
            'value_boolean' => $this->valueBoolean,
            'value_json' => $this->valueJson,
            'unit' => $this->unit,
            'reference_range' => $this->referenceRange,
            'abnormal_flag' => $this->abnormalFlag,
            'notes' => $this->notes,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
