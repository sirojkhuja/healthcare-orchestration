<?php

namespace App\Shared\Infrastructure\Persistence\Concerns;

use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Exceptions\MissingTenantContext;
use App\Shared\Application\Exceptions\TenantScopeViolation;
use App\Shared\Infrastructure\Persistence\Contracts\TenantScopedModel;
use App\Shared\Infrastructure\Persistence\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** @phpstan-require-implements TenantScopedModel */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(app(TenantScope::class));

        static::creating(function (Model $model): void {
            if (! $model instanceof TenantScopedModel) {
                return;
            }

            $tenantContext = app(TenantContext::class);
            $column = $model->getTenantColumnName();
            $tenantId = $model->getAttribute($column);
            $currentTenantId = $tenantContext->tenantId();

            if ($tenantId === null) {
                if ($currentTenantId === null) {
                    throw new MissingTenantContext('Tenant-owned records require tenant context before creation.');
                }

                $model->setAttribute($column, $currentTenantId);

                return;
            }

            if (! is_string($tenantId)) {
                throw new TenantScopeViolation('Tenant-owned records require a string UUID tenant identifier.');
            }

            if (! Str::isUuid($tenantId)) {
                throw new TenantScopeViolation('Tenant-owned records require a valid UUID tenant identifier.');
            }

            $normalizedTenantId = strtolower($tenantId);
            $model->setAttribute($column, $normalizedTenantId);

            if ($currentTenantId !== null && $normalizedTenantId !== $currentTenantId) {
                throw new TenantScopeViolation('Tenant-owned records cannot be written outside the active tenant context.');
            }
        });
    }

    public function getTenantColumnName(): string
    {
        return 'tenant_id';
    }
}
