<?php

namespace App\Modules\Treatment\Domain\TreatmentPlans;

enum TreatmentItemType: string
{
    case ASSESSMENT = 'assessment';
    case PROCEDURE = 'procedure';
    case MEDICATION = 'medication';
    case THERAPY = 'therapy';
    case LAB = 'lab';
    case FOLLOW_UP = 'follow_up';
    case OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
