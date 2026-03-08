<?php

namespace Tests\Fixtures\Models;

use App\Shared\Infrastructure\Persistence\TenantAwareModel;

final class TenantScopedRecord extends TenantAwareModel
{
    public $timestamps = false;

    protected $table = 'tenant_scoped_records';

    protected $guarded = [];
}
