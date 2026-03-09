<?php

namespace App\Modules\TenantManagement\Application\Commands;

final readonly class DeleteClinicHolidayCommand
{
    public function __construct(
        public string $clinicId,
        public string $holidayId,
    ) {}
}
