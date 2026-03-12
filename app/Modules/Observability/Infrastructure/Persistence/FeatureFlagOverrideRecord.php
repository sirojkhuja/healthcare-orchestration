<?php

namespace App\Modules\Observability\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $flag_key
 * @property bool $enabled
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class FeatureFlagOverrideRecord extends Model
{
    protected $table = 'ops_feature_flags';

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
            'enabled' => 'bool',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
