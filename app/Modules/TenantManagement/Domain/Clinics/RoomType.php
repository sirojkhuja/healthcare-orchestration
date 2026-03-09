<?php

namespace App\Modules\TenantManagement\Domain\Clinics;

final class RoomType
{
    public const ADMINISTRATIVE = 'administrative';

    public const CONSULTATION = 'consultation';

    public const IMAGING = 'imaging';

    public const LABORATORY = 'laboratory';

    public const OPERATING = 'operating';

    public const OTHER = 'other';

    public const TREATMENT = 'treatment';

    public const VIRTUAL = 'virtual';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CONSULTATION,
            self::TREATMENT,
            self::IMAGING,
            self::LABORATORY,
            self::OPERATING,
            self::ADMINISTRATIVE,
            self::VIRTUAL,
            self::OTHER,
        ];
    }
}
