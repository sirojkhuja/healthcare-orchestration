<?php

namespace App\Modules\IdentityAccess\Infrastructure\Profiles\Persistence;

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\ProfileRepository;
use App\Modules\IdentityAccess\Application\Data\ProfileAvatarData;
use App\Modules\IdentityAccess\Application\Data\ProfilePatchData;
use App\Modules\IdentityAccess\Application\Data\UserProfileData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use LogicException;

final class DatabaseProfileRepository implements ProfileRepository
{
    #[\Override]
    public function findById(string $userId): ?UserProfileData
    {
        $user = User::query()->find($userId);

        return $user instanceof User ? $this->toData($user) : null;
    }

    #[\Override]
    public function update(string $userId, ProfilePatchData $patch): UserProfileData
    {
        $updates = $patch->toDatabaseUpdates();

        if ($updates === []) {
            return $this->findById($userId) ?? throw new LogicException('The user profile could not be reloaded.');
        }

        $updated = User::query()->whereKey($userId)->update(array_merge(
            $updates,
            ['updated_at' => CarbonImmutable::now()],
        ));

        if ($updated < 1) {
            throw new LogicException('The user profile could not be updated.');
        }

        return $this->findById($userId) ?? throw new LogicException('The user profile could not be reloaded.');
    }

    #[\Override]
    public function updateAvatar(string $userId, ?ProfileAvatarData $avatar): UserProfileData
    {
        $updated = User::query()->whereKey($userId)->update([
            'avatar_disk' => $avatar?->disk,
            'avatar_path' => $avatar?->path,
            'avatar_file_name' => $avatar?->fileName,
            'avatar_mime_type' => $avatar?->mimeType,
            'avatar_size_bytes' => $avatar?->sizeBytes,
            'avatar_uploaded_at' => $avatar?->uploadedAt,
            'updated_at' => CarbonImmutable::now(),
        ]);

        if ($updated < 1) {
            throw new LogicException('The user avatar could not be updated.');
        }

        return $this->findById($userId) ?? throw new LogicException('The user profile could not be reloaded.');
    }

    private function toData(User $user): UserProfileData
    {
        return new UserProfileData(
            userId: $this->stringValue($user->getAttribute('id')),
            name: $this->stringValue($user->getAttribute('name')),
            email: $this->stringValue($user->getAttribute('email')),
            phone: $this->nullableString($user->getAttribute('phone')),
            jobTitle: $this->nullableString($user->getAttribute('job_title')),
            locale: $this->nullableString($user->getAttribute('locale')),
            timezone: $this->nullableString($user->getAttribute('timezone')),
            avatar: $this->avatarData($user),
            updatedAt: $this->dateTime($user->getAttribute('updated_at')),
        );
    }

    private function avatarData(User $user): ?ProfileAvatarData
    {
        $disk = $this->nullableString($user->getAttribute('avatar_disk'));
        $path = $this->nullableString($user->getAttribute('avatar_path'));
        $fileName = $this->nullableString($user->getAttribute('avatar_file_name'));
        $mimeType = $this->nullableString($user->getAttribute('avatar_mime_type'));
        $uploadedAt = $this->nullableDateTime($user->getAttribute('avatar_uploaded_at'));
        $sizeBytes = $user->getAttribute('avatar_size_bytes');

        if (
            $disk === null
            || $path === null
            || $fileName === null
            || $mimeType === null
            || ! is_int($sizeBytes)
            || $uploadedAt === null
        ) {
            return null;
        }

        return new ProfileAvatarData(
            disk: $disk,
            path: $path,
            fileName: $fileName,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
            uploadedAt: $uploadedAt,
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($this->stringValue($value));
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
