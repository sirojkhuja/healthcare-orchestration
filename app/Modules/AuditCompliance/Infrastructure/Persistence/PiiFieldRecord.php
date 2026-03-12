<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\TenantAwareModel;

final class PiiFieldRecord extends TenantAwareModel
{
    protected $table = 'pii_fields';

    protected $guarded = [];

    #[\Override]
    protected function casts(): array
    {
        return [
            'last_rotated_at' => 'immutable_datetime',
            'last_reencrypted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
