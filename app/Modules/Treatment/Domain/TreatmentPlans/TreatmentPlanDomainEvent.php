<?php

namespace App\Modules\Treatment\Domain\TreatmentPlans;

final readonly class TreatmentPlanDomainEvent
{
    public function __construct(
        public TreatmentPlanEventType $type,
        public string $planId,
        public string $tenantId,
        public string $patientId,
        public string $providerId,
        public string $title,
        public TreatmentPlanStatus $status,
        public TreatmentPlanTransitionData $transition,
    ) {}

    /**
     * @return array{
     *     event_type: string,
     *     plan_id: string,
     *     tenant_id: string,
     *     patient_id: string,
     *     provider_id: string,
     *     title: string,
     *     status: string,
     *     transition: array{
     *         from_status: string,
     *         to_status: string,
     *         occurred_at: string,
     *         actor: array{type: string, id: string|null, name: string|null},
     *         reason: string|null
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->type->value,
            'plan_id' => $this->planId,
            'tenant_id' => $this->tenantId,
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'title' => $this->title,
            'status' => $this->status->value,
            'transition' => $this->transition->toArray(),
        ];
    }
}
