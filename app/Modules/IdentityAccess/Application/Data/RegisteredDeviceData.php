<?php

namespace App\Modules\IdentityAccess\Application\Data;

use Carbon\CarbonImmutable;

final readonly class RegisteredDeviceData
{
    public function __construct(
        public string $deviceId,
        public string $installationId,
        public string $name,
        public string $platform,
        public ?string $pushToken,
        public ?string $appVersion,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?CarbonImmutable $lastSeenAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     installation_id: string,
     *     name: string,
     *     platform: string,
     *     push_token: string|null,
     *     app_version: string|null,
     *     ip_address: string|null,
     *     user_agent: string|null,
     *     last_seen_at: string|null,
     *     created_at: string,
     *     updated_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->deviceId,
            'installation_id' => $this->installationId,
            'name' => $this->name,
            'platform' => $this->platform,
            'push_token' => $this->pushToken,
            'app_version' => $this->appVersion,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'last_seen_at' => $this->lastSeenAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
