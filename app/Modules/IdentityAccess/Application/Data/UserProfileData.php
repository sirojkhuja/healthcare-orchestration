<?php

namespace App\Modules\IdentityAccess\Application\Data;

use Carbon\CarbonImmutable;

final readonly class UserProfileData
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $jobTitle,
        public ?string $locale,
        public ?string $timezone,
        public ?ProfileAvatarData $avatar,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     email: string,
     *     phone: string|null,
     *     job_title: string|null,
     *     locale: string|null,
     *     timezone: string|null,
     *     avatar: array{
     *         file_name: string,
     *         mime_type: string,
     *         size_bytes: int,
     *         uploaded_at: string
     *     }|null,
     *     updated_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->userId,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'avatar' => $this->avatar?->toArray(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
