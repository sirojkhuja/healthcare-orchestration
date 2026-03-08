<?php

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Infrastructure\Persistence\Contracts\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class TenantScope implements Scope
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    #[\Override]
    public function apply(Builder $builder, Model $model): void
    {
        if (! $model instanceof TenantScopedModel) {
            return;
        }

        $column = $model->qualifyColumn($model->getTenantColumnName());
        $tenantId = $this->tenantContext->tenantId();

        if ($tenantId === null) {
            if (! app()->runningInConsole()) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $builder->where($column, $tenantId);
    }
}
