<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\RegisteredDeviceData;
use DateTimeInterface;

interface DeviceRepository
{
    public function register(
        string $userId,
        string $installationId,
        string $name,
        string $platform,
        ?string $pushToken,
        ?string $appVersion,
        ?string $ipAddress,
        ?string $userAgent,
        DateTimeInterface $seenAt,
    ): RegisteredDeviceData;

    /**
     * @return list<RegisteredDeviceData>
     */
    public function listForUser(string $userId): array;

    public function deleteForUser(string $deviceId, string $userId): bool;
}
