<?php

namespace App\Shared\Infrastructure\Messaging\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $consumer_name
 * @property string $message_id
 * @property string $topic
 * @property int $partition
 * @property \Carbon\CarbonImmutable $processed_at
 */
final class ConsumerReceiptRecord extends Model
{
    protected $table = 'kafka_consumer_receipts';

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'partition' => 'int',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
