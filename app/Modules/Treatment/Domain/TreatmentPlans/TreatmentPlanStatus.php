<?php

namespace App\Modules\Treatment\Domain\TreatmentPlans;

enum TreatmentPlanStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case FINISHED = 'finished';
    case REJECTED = 'rejected';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
