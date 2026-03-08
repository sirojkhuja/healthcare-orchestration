<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Shared\Infrastructure\Persistence\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property string|null $locale
 * @property string|null $timezone
 * @property string|null $avatar_disk
 * @property string|null $avatar_path
 * @property string|null $avatar_file_name
 * @property string|null $avatar_mime_type
 * @property int|null $avatar_size_bytes
 * @property string $password
 * @property string|null $remember_token
 */
final class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    use HasUuidPrimaryKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'job_title',
        'locale',
        'timezone',
        'avatar_disk',
        'avatar_path',
        'avatar_file_name',
        'avatar_mime_type',
        'avatar_size_bytes',
        'avatar_uploaded_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<array-key, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'avatar_uploaded_at' => 'immutable_datetime',
            'avatar_size_bytes' => 'integer',
            'password' => 'hashed',
        ];
    }
}
