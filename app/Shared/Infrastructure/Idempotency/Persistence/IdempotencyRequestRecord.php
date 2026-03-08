<?php

namespace App\Shared\Infrastructure\Idempotency\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $scope_hash
 * @property string $operation
 * @property string|null $tenant_id
 * @property string|null $actor_id
 * @property string $idempotency_key
 * @property string $request_fingerprint
 * @property string $status
 * @property int|null $response_status
 * @property array<string, list<string>>|null $response_headers
 * @property string|null $response_body
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property \Carbon\CarbonImmutable|null $expires_at
 */
final class IdempotencyRequestRecord extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PROCESSING = 'processing';

    protected $table = 'idempotency_requests';

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
            'response_headers' => 'array',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
