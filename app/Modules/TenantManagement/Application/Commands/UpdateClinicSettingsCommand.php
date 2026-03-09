<?php

namespace App\Modules\TenantManagement\Application\Commands;

use App\Modules\TenantManagement\Application\Data\ClinicSettingsData;

final readonly class UpdateClinicSettingsCommand
{
    public function __construct(
        public string $clinicId,
        public ClinicSettingsData $settings,
    ) {}
}
