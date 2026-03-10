<?php

namespace App\Modules\Treatment\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TreatmentPlanData
{
    /**
     * @param  array<string, mixed>|null  $lastTransition
     */
    public function __construct(
        public string $planId,
        public string $tenantId,
        public string $patientId,
        public string $patientDisplayName,
        public string $providerId,
        public string $providerDisplayName,
        public string $title,
        public ?string $summary,
        public ?string $goals,
        public ?string $plannedStartDate,
        public ?string $plannedEndDate,
        public int $itemCount,
        public string $status,
        public ?array $lastTransition,
        public ?CarbonImmutable $approvedAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $pausedAt,
        public ?CarbonImmutable $finishedAt,
        public ?CarbonImmutable $rejectedAt,
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
            'id' => $this->planId,
            'tenant_id' => $this->tenantId,
            'patient' => [
                'id' => $this->patientId,
                'display_name' => $this->patientDisplayName,
            ],
            'provider' => [
                'id' => $this->providerId,
                'display_name' => $this->providerDisplayName,
            ],
            'title' => $this->title,
            'summary' => $this->summary,
            'goals' => $this->goals,
            'planned_start_date' => $this->plannedStartDate,
            'planned_end_date' => $this->plannedEndDate,
            'item_count' => $this->itemCount,
            'status' => $this->status,
            'last_transition' => $this->lastTransition,
            'approved_at' => $this->approvedAt?->toIso8601String(),
            'started_at' => $this->startedAt?->toIso8601String(),
            'paused_at' => $this->pausedAt?->toIso8601String(),
            'finished_at' => $this->finishedAt?->toIso8601String(),
            'rejected_at' => $this->rejectedAt?->toIso8601String(),
            'deleted_at' => $this->deletedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
