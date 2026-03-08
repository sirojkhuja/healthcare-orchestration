<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth\Persistence;

use Illuminate\Database\Eloquent\Model;

final class MfaChallengeRecord extends Model
{
    public $incrementing = false;

    protected $table = 'mfa_challenges';

    protected $guarded = [];

    protected $keyType = 'string';

    #[\Override]
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
