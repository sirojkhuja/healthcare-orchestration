<?php

namespace App\Modules\Patient\Domain\Patients;

enum PatientSex: string
{
    case FEMALE = 'female';
    case MALE = 'male';
    case OTHER = 'other';
    case UNKNOWN = 'unknown';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (self $sex): string => $sex->value,
            self::cases(),
        );
    }
}
