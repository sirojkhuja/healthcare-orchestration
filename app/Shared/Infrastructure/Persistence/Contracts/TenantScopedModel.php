<?php

namespace App\Shared\Infrastructure\Persistence\Contracts;

interface TenantScopedModel
{
    public function getTenantColumnName(): string;
}
