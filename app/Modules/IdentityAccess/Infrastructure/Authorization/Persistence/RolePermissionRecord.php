<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization\Persistence;

use App\Shared\Infrastructure\Persistence\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $role_id
 * @property string $permission
 */
final class RolePermissionRecord extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'role_permissions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
