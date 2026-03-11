<?php

namespace App\Modules\Insurance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ClaimData
{
    /**
     * @param  list<string>  $serviceCategories
     * @param  list<array<string, mixed>>  $adjudicationHistory
     * @param  array<string, mixed>|null  $lastTransition
     */
    public function __construct(
        public string $claimId,
        public string $tenantId,
        public string $claimNumber,
        public string $payerId,
        public string $payerCode,
        public string $payerName,
        public string $payerInsuranceCode,
        public string $patientId,
        public string $patientDisplayName,
        public string $invoiceId,
        public string $invoiceNumber,
        public ?string $patientPolicyId,
        public ?string $patientPolicyNumber,
        public ?string $patientMemberNumber,
        public ?string $patientGroupNumber,
        public ?string $patientPlanName,
        public string $currency,
        public CarbonImmutable $serviceDate,
        public string $billedAmount,
        public ?string $approvedAmount,
        public ?string $paidAmount,
        public ?string $notes,
        public string $status,
        public int $attachmentCount,
        public array $serviceCategories,
        public ?CarbonImmutable $submittedAt,
        public ?CarbonImmutable $reviewStartedAt,
        public ?CarbonImmutable $approvedAt,
        public ?CarbonImmutable $deniedAt,
        public ?CarbonImmutable $paidAt,
        public ?string $denialReason,
        public ?array $lastTransition,
        public array $adjudicationHistory,
        public ?CarbonImmutable $deletedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->claimId,
            'tenant_id' => $this->tenantId,
            'claim_number' => $this->claimNumber,
            'status' => $this->status,
            'payer' => [
                'id' => $this->payerId,
                'code' => $this->payerCode,
                'name' => $this->payerName,
                'insurance_code' => $this->payerInsuranceCode,
            ],
            'patient' => [
                'id' => $this->patientId,
                'display_name' => $this->patientDisplayName,
            ],
            'invoice' => [
                'id' => $this->invoiceId,
                'number' => $this->invoiceNumber,
            ],
            'policy' => $this->patientPolicyId === null ? null : [
                'id' => $this->patientPolicyId,
                'policy_number' => $this->patientPolicyNumber,
                'member_number' => $this->patientMemberNumber,
                'group_number' => $this->patientGroupNumber,
                'plan_name' => $this->patientPlanName,
            ],
            'currency' => $this->currency,
            'service_date' => $this->serviceDate->toDateString(),
            'service_categories' => $this->serviceCategories,
            'attachment_count' => $this->attachmentCount,
            'amounts' => [
                'billed' => [
                    'amount' => $this->billedAmount,
                    'currency' => $this->currency,
                ],
                'approved' => $this->approvedAmount === null ? null : [
                    'amount' => $this->approvedAmount,
                    'currency' => $this->currency,
                ],
                'paid' => $this->paidAmount === null ? null : [
                    'amount' => $this->paidAmount,
                    'currency' => $this->currency,
                ],
            ],
            'notes' => $this->notes,
            'submitted_at' => $this->submittedAt?->toIso8601String(),
            'review_started_at' => $this->reviewStartedAt?->toIso8601String(),
            'approved_at' => $this->approvedAt?->toIso8601String(),
            'denied_at' => $this->deniedAt?->toIso8601String(),
            'paid_at' => $this->paidAt?->toIso8601String(),
            'denial_reason' => $this->denialReason,
            'last_transition' => $this->lastTransition,
            'adjudication_history' => $this->adjudicationHistory,
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
