<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth\Persistence;

use App\Shared\Infrastructure\Persistence\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $access_token_id
 * @property string $refresh_token_hash
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\CarbonImmutable $access_token_expires_at
 * @property \Carbon\CarbonImmutable $refresh_token_expires_at
 * @property \Carbon\CarbonImmutable|null $last_used_at
 * @property \Carbon\CarbonImmutable|null $revoked_at
 */
final class AuthSessionRecord extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'auth_sessions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'access_token_expires_at' => 'immutable_datetime',
            'refresh_token_expires_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
