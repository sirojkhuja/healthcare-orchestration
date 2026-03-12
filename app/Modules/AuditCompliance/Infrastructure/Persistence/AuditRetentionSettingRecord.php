<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\TenantAwareModel;

final class AuditRetentionSettingRecord extends TenantAwareModel
{
    protected $table = 'audit_retention_settings';

    protected $guarded = [];

    #[\Override]
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
