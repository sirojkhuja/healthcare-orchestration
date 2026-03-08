<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth\Persistence;

use App\Shared\Infrastructure\Persistence\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string $key_prefix
 * @property string $token_hash
 * @property \Carbon\CarbonImmutable|null $last_used_at
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property \Carbon\CarbonImmutable|null $revoked_at
 * @property \Carbon\CarbonImmutable $created_at
 */
final class ApiKeyRecord extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'api_keys';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
