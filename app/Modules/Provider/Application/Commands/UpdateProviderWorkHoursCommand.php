<?php

namespace App\Modules\Provider\Application\Commands;

use App\Modules\Provider\Application\Data\ProviderWorkHoursData;

final readonly class UpdateProviderWorkHoursCommand
{
    public function __construct(
        public string $providerId,
        public ProviderWorkHoursData $workHours,
    ) {}
}
