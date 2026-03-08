<?php

namespace App\Modules\IdentityAccess\Infrastructure\Devices\Persistence;

use App\Shared\Infrastructure\Persistence\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $installation_id
 * @property string $name
 * @property string $platform
 * @property string|null $push_token
 * @property string|null $app_version
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\CarbonImmutable|null $last_seen_at
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class RegisteredDeviceRecord extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'registered_devices';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
