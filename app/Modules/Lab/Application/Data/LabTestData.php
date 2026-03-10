<?php

namespace App\Modules\Lab\Application\Data;

use Carbon\CarbonImmutable;

final readonly class LabTestData
{
    public function __construct(
        public string $testId,
        public string $tenantId,
        public string $code,
        public string $name,
        public ?string $description,
        public string $specimenType,
        public string $resultType,
        public ?string $unit,
        public ?string $referenceRange,
        public string $labProviderKey,
        public ?string $externalTestCode,
        public bool $isActive,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->testId,
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'specimen_type' => $this->specimenType,
            'result_type' => $this->resultType,
            'unit' => $this->unit,
            'reference_range' => $this->referenceRange,
            'lab_provider_key' => $this->labProviderKey,
            'external_test_code' => $this->externalTestCode,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
