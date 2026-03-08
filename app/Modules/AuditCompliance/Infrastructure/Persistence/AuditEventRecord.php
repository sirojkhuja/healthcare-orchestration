<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AuditEventRecord extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'audit_events';

    protected $guarded = [];

    protected $keyType = 'string';

    #[\Override]
    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    #[\Override]
    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Audit events are immutable and cannot be updated.'));
        self::deleting(fn () => throw new LogicException('Audit events are immutable and cannot be deleted.'));
    }
}
