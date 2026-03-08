<?php

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\Concerns\BelongsToTenant;
use App\Shared\Infrastructure\Persistence\Concerns\HasUuidPrimaryKey;
use App\Shared\Infrastructure\Persistence\Contracts\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;

abstract class TenantAwareModel extends Model implements TenantScopedModel
{
    use BelongsToTenant;
    use HasUuidPrimaryKey;
}
