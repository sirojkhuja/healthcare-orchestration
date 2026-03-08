<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth\Persistence;

use Illuminate\Database\Eloquent\Model;

final class MfaCredentialRecord extends Model
{
    public $incrementing = false;

    protected $table = 'mfa_credentials';

    protected $guarded = [];

    protected $keyType = 'string';

    #[\Override]
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_code_hashes' => 'array',
            'enabled_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
