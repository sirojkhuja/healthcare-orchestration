<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth\Persistence;

use App\Modules\IdentityAccess\Application\Contracts\MfaChallengeRepository;
use App\Modules\IdentityAccess\Application\Data\MfaChallengeData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

final class DatabaseMfaChallengeRepository implements MfaChallengeRepository
{
    #[\Override]
    public function create(
        string $userId,
        DateTimeInterface $expiresAt,
        ?string $ipAddress,
        ?string $userAgent,
        string $purpose = 'login',
    ): MfaChallengeData {
        $now = CarbonImmutable::now();
        $record = MfaChallengeRecord::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'purpose' => $purpose,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt,
            'verified_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->toData($record);
    }

    #[\Override]
    public function findActive(string $challengeId, DateTimeInterface $now): ?MfaChallengeData
    {
        /** @var MfaChallengeRecord|null $record */
        $record = MfaChallengeRecord::query()
            ->whereKey($challengeId)
            ->whereNull('verified_at')
            ->where('expires_at', '>', $now)
            ->first();

        return $record instanceof MfaChallengeRecord ? $this->toData($record) : null;
    }

    #[\Override]
    public function markVerified(string $challengeId, DateTimeInterface $verifiedAt): bool
    {
        $updated = MfaChallengeRecord::query()
            ->whereKey($challengeId)
            ->whereNull('verified_at')
            ->update([
                'verified_at' => $verifiedAt,
                'updated_at' => CarbonImmutable::now(),
            ]);

        return $updated > 0;
    }

    private function toData(MfaChallengeRecord $record): MfaChallengeData
    {
        $expiresAt = $this->dateTime($record->getAttribute('expires_at'));
        $verifiedAt = $this->nullableDateTime($record->getAttribute('verified_at'));
        $createdAt = $this->dateTime($record->getAttribute('created_at'));

        return new MfaChallengeData(
            challengeId: $this->stringValue($record->getAttribute('id')),
            userId: $this->stringValue($record->getAttribute('user_id')),
            purpose: $this->stringValue($record->getAttribute('purpose')),
            ipAddress: $this->nullableString($record->getAttribute('ip_address')),
            userAgent: $this->nullableString($record->getAttribute('user_agent')),
            expiresAt: $expiresAt,
            verifiedAt: $verifiedAt,
            createdAt: $createdAt,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullableDateTime(mixed $value): ?DateTimeInterface
    {
        return $value instanceof DateTimeInterface ? $value : null;
    }

    private function dateTime(mixed $value): DateTimeInterface
    {
        return $value instanceof DateTimeInterface ? $value : CarbonImmutable::now();
    }
}
