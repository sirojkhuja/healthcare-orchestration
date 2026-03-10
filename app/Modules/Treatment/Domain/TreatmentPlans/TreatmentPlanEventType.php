<?php

namespace App\Modules\Treatment\Domain\TreatmentPlans;

enum TreatmentPlanEventType: string
{
    case APPROVED = 'treatment_plan.approved';
    case STARTED = 'treatment_plan.started';
    case PAUSED = 'treatment_plan.paused';
    case RESUMED = 'treatment_plan.resumed';
    case FINISHED = 'treatment_plan.finished';
    case REJECTED = 'treatment_plan.rejected';
}
