<?php

namespace App\Modules\Scheduling\Application\Contracts;

interface AvailabilityCacheInvalidator
{
    public function invalidate(string $tenantId): void;
}
