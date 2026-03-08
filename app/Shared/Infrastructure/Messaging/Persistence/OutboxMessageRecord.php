<?php

namespace App\Shared\Infrastructure\Messaging\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $event_id
 * @property string $event_type
 * @property string $topic
 * @property string|null $tenant_id
 * @property string $request_id
 * @property string $correlation_id
 * @property string|null $causation_id
 * @property string|null $partition_key
 * @property array<string, string>|null $headers
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property \Carbon\CarbonImmutable|null $next_attempt_at
 * @property \Carbon\CarbonImmutable|null $claimed_at
 * @property \Carbon\CarbonImmutable|null $delivered_at
 * @property string|null $last_error
 * @property \Carbon\CarbonImmutable $created_at
 */
final class OutboxMessageRecord extends Model
{
    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    protected $table = 'outbox_messages';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'attempts' => 'int',
            'next_attempt_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
