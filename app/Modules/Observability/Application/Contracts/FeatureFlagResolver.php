<?php

namespace App\Modules\Observability\Application\Contracts;

interface FeatureFlagResolver
{
    public function isEnabled(string $key): bool;
}
