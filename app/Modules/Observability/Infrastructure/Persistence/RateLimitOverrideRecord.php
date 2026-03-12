<?php

namespace App\Modules\Observability\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $bucket_key
 * @property int $requests_per_minute
 * @property int $burst
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class RateLimitOverrideRecord extends Model
{
    protected $table = 'ops_rate_limits';

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
            'requests_per_minute' => 'int',
            'burst' => 'int',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
