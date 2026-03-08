<?php

namespace App\Modules\IdentityAccess\Infrastructure\Devices\Persistence;

use App\Modules\IdentityAccess\Application\Contracts\DeviceRepository;
use App\Modules\IdentityAccess\Application\Data\RegisteredDeviceData;
use DateTimeInterface;

final class DatabaseDeviceRepository implements DeviceRepository
{
    #[\Override]
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
    ): RegisteredDeviceData {
        /** @var RegisteredDeviceRecord $record */
        $record = RegisteredDeviceRecord::query()->firstOrNew([
            'user_id' => $userId,
            'installation_id' => $installationId,
        ]);

        $record->fill([
            'name' => $name,
            'platform' => $platform,
            'push_token' => $pushToken,
            'app_version' => $appVersion,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'last_seen_at' => $seenAt,
        ]);
        $record->save();

        return $this->toData($record);
    }

    #[\Override]
    public function listForUser(string $userId): array
    {
        /** @var list<RegisteredDeviceRecord> $records */
        $records = RegisteredDeviceRecord::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $records);
    }

    #[\Override]
    public function deleteForUser(string $deviceId, string $userId): bool
    {
        return RegisteredDeviceRecord::query()
            ->whereKey($deviceId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    private function toData(RegisteredDeviceRecord $record): RegisteredDeviceData
    {
        return new RegisteredDeviceData(
            deviceId: $record->id,
            installationId: $record->installation_id,
            name: $record->name,
            platform: $record->platform,
            pushToken: $record->push_token,
            appVersion: $record->app_version,
            ipAddress: $record->ip_address,
            userAgent: $record->user_agent,
            lastSeenAt: $record->last_seen_at,
            createdAt: $record->created_at,
            updatedAt: $record->updated_at,
        );
    }
}
