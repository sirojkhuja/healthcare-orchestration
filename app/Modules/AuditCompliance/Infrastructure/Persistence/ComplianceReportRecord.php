<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\TenantAwareModel;
use LogicException;

final class ComplianceReportRecord extends TenantAwareModel
{
    protected $table = 'compliance_reports';

    protected $guarded = [];

    #[\Override]
    protected function casts(): array
    {
        return [
            'field_ids' => 'array',
            'summary' => 'array',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    #[\Override]
    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Compliance reports are append-only and cannot be updated.'));
        self::deleting(fn () => throw new LogicException('Compliance reports are append-only and cannot be deleted.'));
    }
}
