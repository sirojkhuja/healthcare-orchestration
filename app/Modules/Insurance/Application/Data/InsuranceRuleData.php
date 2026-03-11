<?php

namespace App\Modules\Insurance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class InsuranceRuleData
{
    public function __construct(
        public string $ruleId,
        public string $tenantId,
        public string $payerId,
        public string $payerCode,
        public string $payerName,
        public string $payerInsuranceCode,
        public string $code,
        public string $name,
        public ?string $serviceCategory,
        public bool $requiresPrimaryPolicy,
        public bool $requiresAttachment,
        public ?string $maxClaimAmount,
        public ?int $submissionWindowDays,
        public bool $isActive,
        public ?string $notes,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->ruleId,
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'name' => $this->name,
            'payer' => [
                'id' => $this->payerId,
                'code' => $this->payerCode,
                'name' => $this->payerName,
                'insurance_code' => $this->payerInsuranceCode,
            ],
            'service_category' => $this->serviceCategory,
            'requires_primary_policy' => $this->requiresPrimaryPolicy,
            'requires_attachment' => $this->requiresAttachment,
            'max_claim_amount' => $this->maxClaimAmount,
            'submission_window_days' => $this->submissionWindowDays,
            'is_active' => $this->isActive,
            'notes' => $this->notes,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
